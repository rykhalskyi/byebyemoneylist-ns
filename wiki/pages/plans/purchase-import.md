---
created: 2026-11-09
type: plan
status: in_progress
summary: Purchase import inbox — one-way phone upload, server-side category/store/product mapping with optional AI suggestions, review and approval into a normal list.
---

# Purchase Import Inbox — Implementation Plan

Companion client plan: `~/Source/byebyemoneylist/wiki/pages/plans/16-purchase-upload-integration.md`.

This is the server half of "Nextcloud integration 2.0": the Android app uploads a
finished purchase list as raw text; this app stores it in an **incoming inbox**,
suggests category/store/product mappings (optionally with the active LLM profile),
lets the user review everything, and only then creates a normal shopping list with
the client's list id.

The old bidirectional mirror (tickets 09, Android plans 06–09) is not revived.
There is no phone-facing read API, no update propagation and no deletes.

## Goal

`POST /api/purchase-imports` accepts the shared JSON contract, deduplicates by
client list id, and produces a reviewable incoming list. The web UI gains an
**Inbox** where the user maps the store, list categories and each item to existing
catalog entries or creates new ones, then approves. Approval creates the real
`bbml_lists`/`bbml_list_items` rows atomically, using the client's UUID as the list
id.

## Decisions

- **D1 — Dedicated inbox tables.** Incoming lists and items live in
  `bbml_purchase_imports`, `bbml_purchase_import_items` and
  `bbml_purchase_import_categories`, not in the live catalog tables. The raw JSON
  is also stored for audit and replay.
- **D2 — The client list UUID is the final list id.** The incoming dedupe key is
  `(owner, client_list_id)`; on approval the new `bbml_lists.id` is that same
  `client_list_id`. Resends are idempotent.
- **D3 — Pending imports are replaceable; approved imports are immutable.** While
  status is `pending`/`in_review`, a resend replaces raw content and recomputes
  suggestions. Once `approved`, any further upload for the same id returns
  "already imported" (200 with `duplicate: true`).
- **D4 — Deterministic matching first.** Exact barcode, exact name/alias and the
  existing `ReceiptProductMatcher` fuzzy logic pre-fill suggestions. No mutations
  happen until approval.
- **D5 — AI only suggests.** Reuse the active `LlmProfileEntity`; AI may fill
  unresolved store/category/product suggestions but never approves or writes
  catalog rows.
- **D6 — Approve is one transaction.** List, items, list-category junction, new
  store/category/product/alias rows and the import status update commit together.
- **D7 — Amounts.** The contract sends major units (`1.29`). `final_total` is
  authoritative; `unit_price`/`discount` are per-line metadata.
- **D8 — Coupons default to skip.** `isCoupon` items are shown for transparency but
  are not imported unless the user explicitly maps them.
- **D9 — One-way auth.** OCS Basic Auth with a Nextcloud app password
  (`#[NoAdminRequired] #[ApiRoute]`), same as the rest of the app API.
- **D10 — New top-level Inbox view.** Review is a multi-step flow, so it gets its
  own menu item rather than being folded into the Shopping Lists page.

## Shared JSON contract (`bbml.purchase-upload/1`)

Identical to the client plan; keep both in sync.

```json
{
  "schema": "bbml.purchase-upload/1",
  "clientId": "5c1e1f7a-7d9c-4f0e-9a1b-3f0a7d1e2b44",
  "listId": "b1f0c7d2-3b1e-4a67-9c55-0e1d2f3a4b5c",
  "list": {
    "name": "Purchase 09.11.2026",
    "createdAt": "2026-11-09T18:30:00Z",
    "purchaseDate": "2026-11-09T19:05:00Z",
    "currency": "€",
    "finalTotal": 42.37,
    "store": { "name": "Lidl", "address": "Hauptstr. 1", "receiptName": "LIDL" },
    "categories": [{ "name": "Groceries", "isIncome": false }]
  },
  "items": [
    {
      "lineId": "1728571200000",
      "name": "Milk 1l",
      "quantity": 1.0,
      "unitPrice": 1.29,
      "discount": null,
      "isCoupon": false,
      "barcode": "4001234567890",
      "categoryHint": "Dairy"
    }
  ]
}
```

Validation:

- `schema` must equal `bbml.purchase-upload/1` (422 otherwise).
- `listId` must be a UUID v4; `name` non-empty after trim.
- `quantity` > 0; `unitPrice`/`discount` non-negative and finite.
- Max 1000 items, max 1 MB request body.
- Dates must parse as RFC 3339; invalid → 422.
- Unknown fields are ignored.

## Phase 1 — Schema

Use the next free migration version. At planning time `1012` is free but reserved by
the To Buy plan; if that lands first, use `1013`. Suggested filename
`Version1012Date20261109.php`.

### `bbml_purchase_imports`

| Column | Type | Notes |
|---|---|---|
| `id` | string(36) PK | server import id (UUID v4) |
| `owner` | string(64) | Nextcloud user id |
| `client_list_id` | string(36) | client UUID; part of the dedupe key |
| `client_id` | string(64) nullable | phone install id, diagnostics only |
| `schema_version` | string(32) | `bbml.purchase-upload/1` |
| `name` | string(255) | list name |
| `status` | string(16) | `pending`, `in_review`, `approved`, `discarded` |
| `store_name` / `store_address` | string(255) / string(512) nullable | raw values |
| `currency` | string(8) nullable | display symbol |
| `final_total` | decimal(12,2) nullable | |
| `list_created_at` / `purchase_date` | datetime nullable | UTC |
| `raw_json` | text | original payload |
| `mapped_store_id` | string(36) nullable | chosen existing store |
| `store_action` | string(16) default `pending` | `pending`, `map_existing`, `create_new`, `skip` |
| `suggested_store_id` | string(36) nullable | |
| `suggested_store_name` | string(255) nullable | |
| `store_match_source` | string(16) nullable | `exact`, `fuzzy`, `ai` |
| `store_confidence` | decimal(4,3) nullable | |
| `approved_list_id` | string(36) nullable | set on approval |
| `created_at` / `updated_at` / `approved_at` | datetime | |

Indexes: unique `(owner, client_list_id)` (`bbml_pi_owner_client_uniq`), index
`(owner, status)` (`bbml_pi_owner_status_idx`).

### `bbml_purchase_import_items`

| Column | Type | Notes |
|---|---|---|
| `id` | string(36) PK | |
| `import_id` | string(36) | index `bbml_pii_import_idx` |
| `owner` | string(64) | |
| `position` | int | matches payload order |
| `line_id` | string(64) nullable | client line id, diagnostics |
| `raw_name` | string(255) | |
| `quantity` | decimal(12,3) | |
| `unit_price` / `discount` | decimal(12,2) nullable | |
| `is_coupon` | bool | |
| `barcode` | string(64) nullable | |
| `category_hint` | string(255) nullable | |
| `action` | string(16) default `pending` | `pending`, `map_existing`, `create_new`, `skip` |
| `mapped_product_id` | string(36) nullable | |
| `mapped_category_id` | string(36) nullable | for `create_new` |
| `new_product_name` | string(255) nullable | for `create_new` |
| `suggested_product_id` | string(36) nullable | |
| `suggested_product_name` | string(255) nullable | |
| `suggested_category_id` | string(36) nullable | |
| `match_source` | string(16) nullable | `barcode`, `exact`, `alias`, `fuzzy`, `ai` |
| `confidence` | decimal(4,3) nullable | |
| `created_at` / `updated_at` | datetime | |

### `bbml_purchase_import_categories`

| Column | Type | Notes |
|---|---|---|
| `id` | string(36) PK | |
| `import_id` | string(36) | index `bbml_pic_import_idx` |
| `owner` | string(64) | |
| `raw_name` | string(255) | |
| `is_income` | bool | |
| `action` | string(16) default `pending` | `pending`, `map_existing`, `create_new`, `skip` |
| `mapped_category_id` | string(36) nullable | |
| `suggested_category_id` | string(36) nullable | |
| `match_source` | string(16) nullable | |
| `confidence` | decimal(4,3) nullable | |

## Phase 2 — Entities, mappers, endpoints

New classes under `lib/`:

- `Entity/PurchaseImportEntity.php`, `PurchaseImportItemEntity.php`,
  `PurchaseImportCategoryEntity.php`
- `Db/PurchaseImportMapper.php`, `PurchaseImportItemMapper.php`,
  `PurchaseImportCategoryMapper.php`
- `Controller/PurchaseImportController.php`

### Endpoints (all `#[NoAdminRequired] #[ApiRoute]`, OCS auto-wrapped)

| Method | URL | Purpose |
|---|---|---|
| `POST` | `/api/purchase-imports` | Accept the contract; create or replace an unapproved import. 201 created / 200 replaced. Response `{ import, duplicate }`. |
| `GET` | `/api/purchase-imports` | List imports for the current user (newest first), optionally `?status=`. |
| `GET` | `/api/purchase-imports/{id}` | Detail: import, items, categories, current suggestions. |
| `POST` | `/api/purchase-imports/{id}/suggest` | Deterministic matching; persists suggestions, status → `in_review`. |
| `POST` | `/api/purchase-imports/{id}/suggest-ai` | LLM suggestions for unresolved rows; 409 when no active profile. |
| `PUT` | `/api/purchase-imports/{id}/mapping` | Save user choices (store, categories, per-item action/product/new name/skip). |
| `POST` | `/api/purchase-imports/{id}/approve` | Create the real list + items atomically; 409 if already imported. |
| `DELETE` | `/api/purchase-imports/{id}` | Discard a pending import (hard delete its items/categories, or status `discarded`). |

`POST` handler outline:

1. Auth user; validate JSON depth/size.
2. Validate `schema`, `listId` (UUID), `list.name`, amounts, dates, item count.
3. Look up by `(owner, client_list_id)`.
   - Not found → insert import + raw items/categories, status `pending`, 201.
   - Found and status `approved`/`discarded` → 200 `duplicate: true`, no change.
   - Found and `pending`/`in_review` → replace items/categories/raw JSON, reset
     suggestions to `pending`, status `pending`, 200.
4. Store raw JSON text regardless of structured columns.

## Phase 3 — Matching

### Deterministic (`PurchaseImportMatcher`)

- **Store.** Normalise the raw name; match by exact name, then `receipt_name` if
  available (phone sends it), then fuzzy name using the same normalisation as
  `ReceiptProductMatcher`. If none, `suggested_store_name = raw name`.
- **Categories.** Exact case-insensitive name among the user's categories, then
  fuzzy. Use `categoryHint` per item; list categories come from
  `list.categories`.
- **Products.** Exact barcode first, then exact name, then aliases, then
  `ReceiptProductMatcher::match()` over the owner's products + alias map.
  - Never match another user's product.
  - Set `suggested_product_id`, `match_source`, `confidence`.
  - No match → `suggested_product_name = raw_name` and category from
    `categoryHint` (mapped or suggested).
- Suggestion defaults are only applied where the row is still `pending`; never
  overwrite a user's `map_existing`/`create_new`/`skip`.

### AI (`PurchaseImportAiService`)

- Load `LlmProfileMapper->findActiveByOwner()`; decrypt the key with `ICrypto`
  (same pattern as `ReceiptScanService`).
- Add a text-only `complete(string $provider, string $apiKey, string $model,
  string $prompt, int $connect, int $read, int $maxTokens): string` to
  `Service/Receipt/OpenAiCompatibleScanner` (reuse `ENDPOINTS`, `DEFAULT_MODELS`,
  error handling, `response_format=json_object`; no image part).
- The web review calls this only when the user presses **Suggest**; nothing is sent
  to the LLM on open, so deterministic exact/barcode/alias matches are not
  overwritten and user edits are never clobbered by a late response.
- Prompt: compact JSON with the import (store name/address, list category names,
  items) plus candidate catalog:
  - stores: `{id, name, address}`
  - categories: `{id, name, income}`
  - products: `{id, name, barcode, categoryId}` (cap at ~300; prefer products whose
    names share a token with the import)
- Ask for JSON:
  ```json
  {
    "storeId": "…|null",
    "newStoreName": "…|null",
    "categories": [{ "rawName": "…", "categoryId": "…|null", "newCategoryName": "…|null" }],
    "items": [{ "lineId": "…", "productId": "…|null", "newProductName": "…|null", "categoryId": "…|null", "confidence": 0.0 }]
  }
  ```
- Validate every returned id belongs to the user; clamp confidence; ignore unknown
  ids. Apply only to rows that are still `pending`, and only when confidence
  ≥ 0.60 (store/category may use a lower bar if the id is exact after validation).
- Never auto-approve.

## Phase 4 — Approval

`PurchaseImportApproveService::approve(importId, userId): ?ListEntity`

1. Load import scoped by owner; require status `in_review` or `pending`; reject
   `approved` with a duplicate signal.
2. If a `bbml_lists` row already exists with `id == client_list_id` and owner →
   return duplicate/409, do not overwrite.
3. In one transaction:
   - **Store:** `map_existing` (verify owner) / `create_new`
     (`StoreEntity` name = raw/suggested name, address) / `skip`.
   - **Categories:** resolve each row: existing → use; create-new → create
     `CategoryEntity` (name, `income` flag, default color/status); skip → omit.
   - **List:** `id = client_list_id`, owner, name, storeId, primary category =
     first mapped, `status = finished`, `is_finished = true`, `kind = PURCHASE`
     when the To Buy kind column exists, `created_at = list_created_at ?? now`,
     `purchase_date = purchase_date ?? now`, `updated_at = now`,
     `final_total = payload finalTotal ?? sum(imported lines)`, position =
     `MAX(position)+1`.
   - **Items:** for each row:
     - `map_existing` → verified product id.
     - `create_new` → create `ProductEntity` (name = `new_product_name` ?:
       `raw_name`, categoryId, status `added`), plus a `ProductAliasEntity` for
       `raw_name` when it differs.
     - `skip` / coupon → omit.
     - Insert `ListItemEntity` with `price = unit_price`, `discount`, `quantity`,
       `is_checked = true`, `status = added`, `position`.
   - List-category junction via `ListMapper::replaceCategoriesByListId`.
   - Import → `approved`, `approved_list_id`, `approved_at`.
4. Return the created list (and let the controller serialize it like
   `ListController::serializeList`).

Edge cases:

- Empty items after skipping coupons → still create the list with `final_total`.
- Duplicate category names across import rows → reuse the first created id.
- `create_new` product whose name already exists for the owner → reuse the existing
  product instead of duplicating (re-query by name inside the transaction).

## Phase 5 — Web UI

### Navigation

- `src/App.vue`: add `{ id: 'imports', label: t('Inbox'), icon: mdiInbox }` and
  render `Imports`.
- Optional: pending count badge fetched on mount.

### API/types

- `src/services/purchaseImportsApi.ts`:
  `fetchPurchaseImports`, `fetchPurchaseImport`, `suggestImport`,
  `suggestImportAi`, `saveImportMapping`, `approveImport`, `discardImport`.
- `src/types.ts`: `PurchaseImport`, `PurchaseImportItem`,
  `PurchaseImportCategory`, `ImportMappingPayload`.

### Views/components

- `src/views/Imports.vue`
  - Loads the list; rows show name, purchase date, store name, item count, status
    chip, and actions **Review** / **Discard**.
  - Empty state "No incoming lists".
- `src/components/imports/ImportReview.vue`
  - Header: list name, raw store name, final total, status.
  - **Store card:** suggestion with confidence; `NcSelect` of existing stores; radio
    "Create new" (editable name/address) / "Skip".
  - **Categories:** chips for raw list categories + distinct `categoryHint` values;
    each maps to an existing category or "Create new" or "Skip".
  - **Items table:** raw name, qty, unit price, discount, coupon badge, and a
    mapping cell:
    - product search (`fetchProducts` / existing catalog search) with the suggested
      product preselected;
    - action toggle: Map existing / Create new / Skip;
    - for Create new: editable product name + category select;
    - coupon rows default to Skip with an explanatory hint.
  - A hint above the sections tells the user that **Suggest** maps products and
    categories automatically. Suggestions run only on demand; failures keep the
    deterministic suggestions visible.
  - Buttons: **Suggest** (runs AI when a profile exists, deterministic otherwise),
    **Approve**. Approve first saves then calls `/approve`; on success emits
    `approved` and the parent reloads.
- `src/views/ShoppingLists.vue` is unchanged; after approval the new list appears in
  the normal list fetch.

### Localization

- Add all strings to `l10n/en.json`, `l10n/de.json`, `l10n/uk.json` via `t()` keys;
  run `npm run l10n`.
- Follow existing date/number formatting helpers (`getCanonicalLocale`).

## Phase 6 — Tests & verification

Server:

```bash
composer lint
composer cs:check
composer psalm
composer test:unit
npm run lint
npm run stylelint
npm run test
npm run build
npm run l10n
composer openapi        # regenerate openapi.json for the new routes
```

New PHPUnit tests:

- `PurchaseImportControllerTest` — auth, schema validation, create vs replace,
  approved duplicate, list/detail, mapping save, suggest, suggest-ai without a
  profile (409), approve, discard.
- `PurchaseImportMatcherTest` — store/category/product exact, barcode, alias,
  fuzzy, no-match suggestions.
- `PurchaseImportApproveServiceTest` — transaction, client id preserved, store and
  category create/reuse, product create/reuse, coupon skip, `final_total` fallback,
  duplicate list id rejected.
- `PurchaseImportAiServiceTest` — prompt building, invalid ids ignored, confidence
  gate, no profile.
- Extend `tests/bootstrap.php` autoloading if needed.

Manual end-to-end (with the Android branch):

1. On a `nextcloud.local` user with an active LLM profile, upload a finished
   purchase from the phone.
2. Inbox shows the import; pressing Suggest fills matches (AI when a profile is
   active, deterministic otherwise).
3. Correct one item to "Create new", skip a coupon, approve.
4. Shopping Lists shows the new list with the same id; Analytics/Dashboard include
   its `final_total`.
5. Resend the same phone list → "already imported", no duplicate.
6. Upload with no active LLM profile → deterministic suggestions still work; AI
   button reports the missing profile.

## File manifest

| # | File | Action |
|---|---|---|
| 1 | `lib/Migration/VersionXXXXDate20261109.php` | Create (3 tables) |
| 2 | `lib/Entity/PurchaseImportEntity.php` | Create |
| 3 | `lib/Entity/PurchaseImportItemEntity.php` | Create |
| 4 | `lib/Entity/PurchaseImportCategoryEntity.php` | Create |
| 5 | `lib/Db/PurchaseImportMapper.php` | Create |
| 6 | `lib/Db/PurchaseImportItemMapper.php` | Create |
| 7 | `lib/Db/PurchaseImportCategoryMapper.php` | Create |
| 8 | `lib/Controller/PurchaseImportController.php` | Create |
| 9 | `lib/Service/Import/PurchaseImportMatcher.php` | Create |
| 10 | `lib/Service/Import/PurchaseImportAiService.php` | Create |
| 11 | `lib/Service/Import/PurchaseImportApproveService.php` | Create |
| 12 | `lib/Service/Receipt/OpenAiCompatibleScanner.php` | Modify (text completion) |
| 13 | `src/App.vue` | Modify (Inbox menu) |
| 14 | `src/views/Imports.vue` | Create |
| 15 | `src/components/imports/ImportReview.vue` | Create |
| 16 | `src/components/imports/MappingRow.vue` (optional) | Create |
| 17 | `src/services/purchaseImportsApi.ts` | Create |
| 18 | `src/types.ts` | Modify |
| 19 | `l10n/{en,de,uk}.json` | Modify |
| 20 | `openapi.json` | Regenerate |
| 21 | `tests/unit/Controller/PurchaseImportControllerTest.php` | Create |
| 22 | `tests/unit/Service/PurchaseImportMatcherTest.php` | Create |
| 23 | `tests/unit/Service/PurchaseImportApproveServiceTest.php` | Create |
| 24 | `tests/unit/Service/PurchaseImportAiServiceTest.php` | Create |

## Risks

| Risk | Impact | Mitigation |
|---|---|---|
| Phone and server contracts drift | Broken uploads | Schema string + duplicated contract in both plans + controller tests. |
| Duplicate imports | Double-counted spending | `(owner, client_list_id)` unique + approved immutability + `bbml_lists.id` guard. |
| Client UUID collides with an existing list id | Approval fails or overwrites | Check owner + id before insert; return 409; never update an existing list. |
| AI hallucinates ids | Wrong mappings | Validate ownership, confidence gate, suggestions only, user approves. |
| Large catalog prompt | Slow/expensive AI | Cap products, prefilter by tokens, text-only call with the profile's max tokens. |
| Migration version collision with the To Buy plan | Deploy failure | Use the next free version at implementation time (`1013` if To Buy `1012` lands first). |
| Non-atomic approval | Partial list | Single DB transaction; rollback on any failure. |
| Coupons counted as products | Inflated item analytics | Default skip; `final_total` authoritative. |

## Out of scope

- Any endpoint that pushes data back to the phone.
- Updating/deleting an approved list from the phone.
- Receipt images for imported lists (the phone sends JSON only; it may already have
  a receipt picture, but this contract does not transfer it).
- Cross-user sharing of imported lists (they are ordinary owner lists; the existing
  share feature can share them afterwards).
- Reworking the removed bidirectional sync or its `serverId` model.
- Auto-approval, background import polling, and editing the raw payload on the
  server.

## Open questions

1. **Resend after approval.** Current decision: reject as duplicate. A later
   "revision" flow would need a new incoming row and a revision link, because a
   list id is immutable.
2. **Currency code.** Only the display symbol is sent. Add `currencyCode` if server
   analytics needs an ISO code.
3. **Coupon handling.** Default skip. If item-level coupon analytics is required,
   add a dedicated "Coupon" product mapping.
4. **Inbox notifications.** A badge/count is optional; no push or email in v1.

## Updates

- [2026-11-10]: **Slice B implemented.** Added fuzzy store/category/product matching
  (`ReceiptProductMatcher::findFuzzyName`), text-only `OpenAiCompatibleScanner::complete`,
  `PurchaseImportAiService`, `PurchaseImportApproveService` (client UUID preserved,
  atomic list/items/category/store/product/alias creation, coupon skip, `final_total`
  fallback), controller endpoints `POST /{id}/suggest`, `POST /{id}/suggest-ai`,
  `PUT /{id}/mapping`, `POST /{id}/approve`, `DELETE /{id}` (soft discard), a web
  **Inbox** with review/mapping UI, l10n en/de/uk, and openapi regeneration.
  Verification: PHPUnit 275/275 (new matcher fuzzy, AI, approval and controller
  tests), psalm, php-cs-fixer, PHP lint, eslint, stylelint, Vitest 230/230 and the
  Vite build all green. `composer openapi` output matched the previous spec except
  the new routes and the updated `purchase_import` tag description.
