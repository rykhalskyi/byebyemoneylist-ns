---
created: 2026-09-14
type: ticket
status: proposed
summary: T3/T11 — Scan receipt in the Purchase dialog: LLM OCR, review, and save
---

# T3/T11 — Scan receipt in the Purchase dialog

Part of [epics/nextcloud-web-app](../epics/nextcloud-web-app.md).
Source: `wiki/raw/04-scan-reciept.md` §Purchase Dialog. Reference: Android
`ui/components/scanner/*` and `data/LlmProfile.kt`.

## Spec

In the Purchase dialog's **Scan receipt** tab the user uploads a receipt photo,
which is sent server-side to the **active LLM profile** for OCR. The parsed result
is shown as an editable **review list**; on confirmation the purchase is saved as a
finished list (`isFinished`, `finalTotal`, `purchaseDate`) with items, and the
source image is stored on the list when **Save receipt** is checked.

Requirements from the source doc:

- Upload a receipt in the Scan tab and send it to the chosen LLM for scan.
- Strip EXIF metadata from the image.
- Send a category list and the **top 5 stores** alongside the image.
- LLM assigns a category to each scanned product and a store to the list.
- Scanned product names are treated as aliases (duplicates get merged later).
- LLM returns a well-defined JSON; scanned products are matched by name and alias.
- Show the result as a list for review/approve; show the purchase total.
- On confirmation save the list as `isFinished`.
- A checked **Save receipt** checkbox stores the source image with the list.

### Non-goals (this ticket)

- Android's ML-Kit local fallback and multi-part (overlapping) receipts.
- Product-merge UI (separate epic item in the catalog).
- Async/background scanning, rate limiting, cost tracking.
- New image capture on desktop (file picker only; camera input on mobile via
  `capture` attribute where supported).

### Key decisions

- **LLM calls happen server-side.** API keys are stored encrypted (`ICrypto`) and
  masked; the browser must never receive them. The editor: new backend scan/commit
  endpoints call the provider with the decrypted key of the **active** profile.
- **EXIF stripped client-side** before upload: draw the image into a `<canvas>`,
  downscale to max 1024 px, export JPEG q≈0.6 (mirrors Android
  `bitmapToBase64`). This removes EXIF and shrinks the payload; the backend still
  re-validates the real MIME and size. The stored receipt is the normalized image.
- **Provider strategy**: one `ReceiptScannerInterface` with three adapters:
  OpenAI-compatible chat-completions (deepseek, siliconflow, openai, grok),
  Gemini `generateContent`, and Anthropic Messages. Prompt, JSON contract and
  response parsing are shared.
- **Read-only scan, transactional commit**: scan returns matched ids without side
  effects; `POST /api/receipts/commit` performs store/list/product/item/price
  writes in one DB transaction (mirrors Android `processPurchase`).
- **Unknown categories are not auto-created**: the LLM category string is mapped
  to an existing category by name; unmatched → uncategorized. Avoids silently
  polluting the catalog. (Open question.)
- **Coupons**: `bbml_list_items.product_id` is non-null and must reference a real
  product, so coupon lines fall back to a per-user `Coupon` product while the
  coupon text is kept in `custom_name`. (Open question.)

## API design

```
POST /api/receipts/scan     multipart "receipt"; uses the active profile
  -> { scan: { storeName?, storeAddress?, storeId?, totalSum?,
       items: [{ name, quantity, price, discount?, isCoupon,
                 productId?, categoryId? }], profile: {id,name,provider} } }
  422 no/oversized/unsupported image, no categories?; 409 no active profile;
  502 provider/parse failure

POST /api/receipts/commit   multipart "payload" JSON + optional "receipt" image
  payload = { name, storeId?, storeName?, storeAddress?, categoryIds?,
              finalTotal, purchaseDate, saveReceipt,
              items: [{ productId?, name, quantity, price, discount?,
                        isCoupon?, categoryId? }] }
  -> { list: <same shape as ListController::serializeList> }
  422 validation; 404 unknown product ids; 500, 502 storage/LLM
```

`GET /api/llm-profiles` already exists for the Settings page; commit needs no new
profile route. `POST /api/lists/{id}/receipt` is reused conceptually but the commit
endpoint stores the image itself so the list id is available in one transaction.

## Plan

Ordered phases; each ends green (`npm run test/lint/build`, `composer run
test:unit/cs:check/psalm`, `composer run openapi`).

### Phase 1 — Backend scan (no persistence)

1. `lib/Service/Receipt/ReceiptScannerInterface.php` (new): `scan(profile, image
   bytes, mime, categories, stores): array`.
2. `lib/Service/Receipt/OpenAiCompatibleScanner.php` (new) — endpoint map for
   deepseek/siliconflow/openai/grok; builds the vision `chat/completions` payload
   and parses `choices[0].message.content`.
3. `lib/Service/Receipt/GeminiScanner.php`, `AnthropicScanner.php` (new).
4. `lib/Service/Receipt/ReceiptScanService.php` (new): loads the active profile
   (`LlmProfileMapper::findActiveByOwner`), decrypts the key, builds the prompt
   (port `LlmScannerConstants`), calls the adapter via `IClientService` with the
   profile timeouts, parses/validates the JSON contract
   (`store_name/store_address/items[]/total_sum`), normalizes numbers.
5. Product matching: `lib/Service/Receipt/ReceiptMatcher.php` (new) — exact
   name/alias (case-insensitive), then Levenshtein fuzzy (port Android
   `ProductMatcher`), using `ProductMapper` + `ProductAliasMapper`; category name →
   `CategoryMapper` exact match.
6. `StoreMapper::findTopByOwner(userId, 5)` (new) — rank by number of lists using
   the store, fallback alphabetical.
7. `lib/Controller/ReceiptScanController.php` (new): `POST /api/receipts/scan`,
   validates image (reuse `ReceiptImageController` rules), 409 when no active
   profile, 502 on provider error.

### Phase 2 — Backend commit (transactional save)

8. `lib/Service/Receipt/ReceiptCommitService.php` (new): resolve/create store
   (address too), create-or-finish list (`isFinished`, `finalTotal`,
   `purchaseDate`, `categoryIds`), per item match/create product + alias, insert
   list item (price, quantity, discount, customName), upsert product price; store
   the receipt via `ReceiptPictureService` when `saveReceipt`; all in one
   `beginTransaction`/`commit` with rollback.
9. `lib/Controller/ReceiptCommitController.php` (new): `POST /api/receipts/commit`
   (multipart JSON + optional image), returns the serialized list.
10. `composer run openapi`; add `tests/unit/Service/Receipt/*Test.php` (mock
    `IClientService`) and controller tests, including rollback and no-profile cases.

### Phase 3 — Frontend

11. `src/utils/image.ts` (new): `normalizeReceiptImage(file)` → canvas,
    1024 px, JPEG.
12. `src/services/receiptApi.ts` (new): `scanReceipt(blob)`, `commitReceipt(payload,
    image?)`; `src/types.ts`: scan/commit types.
13. `src/components/ReceiptReviewDialog.vue` (new): store name/address, total,
    per-item name/qty/unit-price/discount/category, include checkbox, delete,
    select/deselect all, computed sum; emits `confirm(reviewed)`.
14. `src/components/PurchaseDialog.vue`: Scan tab flow — attach/capture →
    normalize → `scanReceipt` (loading/empty/error, no-profile CTA to Settings) →
    review dialog → **Save receipt** checkbox (default checked, shown when a file is
    attached) → `commitReceipt` → emit `saved`, close. Replace the current
    "attach only" scan save path.
15. l10n `en/de/uk` + `npm run l10n`.
16. Vitest: `image.spec.ts`, `receiptApi` mapping, `ReceiptReviewDialog.spec.ts`,
    PurchaseDialog scan-flow tests (mocked API).

### Risks / open questions

- **Provider breadth**: all six providers in v1, or DeepSeek + SiliconFlow first
  (per the doc's "for the beginning")? All six = 3 adapters.
- **Coupons**: dedicated `Coupon` product vs. nullable `product_id` migration.
- **Unknown categories**: map-only (proposed) vs. auto-create (Android behaviour).
- **Prompt size/cost**: category + store lists are small; cap long receipts.
- **Image double upload** (scan then commit) — acceptable; could return a temp
  token later to avoid it.
- **Store ranking** query touches `bbml_lists`; keep it a single grouped query.

## Outcome

**Implemented (2026-09-14).** One-form scan tab: attach → scan → read-only result →
Save. No separate review dialog (user decision); items are editable after saving.

Backend:

- `lib/Service/Receipt/OpenAiCompatibleScanner.php` — DeepSeek + SiliconFlow
  vision chat-completions adapter (IClientService).
- `lib/Service/Receipt/ReceiptProductMatcher.php` — Android `ProductMatcher` port
  (exact name/alias, then Levenshtein + token Jaccard fuzzy).
- `lib/Service/Receipt/ReceiptScanService.php` — loads the active profile,
  decrypts the key, builds the prompt (categories + top 5 stores), calls the
  provider, parses the JSON contract, matches products/categories.
- `lib/Service/Receipt/ReceiptCommitService.php` — transactional save: store,
  finished list, product match/create + alias, list items, price upsert, optional
  receipt image; unknown categories `getOrCreate`d (Android parity); coupon lines
  fall back to a shared `Coupon` product with the text in `custom_name`.
- `lib/Controller/ReceiptScanController.php` — `POST /api/receipts/scan` (409 no
  profile, 502 provider failure).
- `lib/Controller/ReceiptCommitController.php` — `POST /api/receipts/commit`
  (multipart JSON `payload` + optional `receipt` image).
- `StoreMapper::findTopByOwner(5)`, `CategoryMapper::findByNameAndOwner` /
  `getOrCreate`.
- Unit tests: `ReceiptScanServiceTest`, `ReceiptCommitServiceTest`,
  `ReceiptScanControllerTest`, `ReceiptCommitControllerTest`.

Frontend:

- `src/utils/image.ts` — `normalizeReceiptImage` (canvas downscale ≤1024 px, JPEG
  q0.6; drops EXIF/GPS).
- `src/services/receiptApi.ts` — `scanReceipt`, `commitReceipt`.
- `src/types.ts` — `ScannedReceipt`, `ScannedReceiptItem`, `ReceiptCommitPayload`.
- `src/components/PurchaseDialog.vue` — Scan tab: attach/preview, **Scan receipt**
  (`Scanning…`/errors/no-profile hint), read-only result (store, total, items),
  **Save receipt** checkbox, **Re-scan**; Save commits and emits `saved`. The Scan
  tab is **disabled with a hint** when no active LLM profile exists (checked via
  `fetchLlmProfiles` on open; falls back to Manual input if the dialog opened on
  Scan).
- l10n `en/de/uk` (13 new strings).
- Vitest: `tests/js/utils/image.spec.ts`,
  `tests/js/services/receiptApi.spec.ts`, and the rewritten
  `tests/js/components/PurchaseDialog.spec.ts`.

Phase 4 — shopping-list receipt viewer (completes the epic's "Shopping lists"
bullet; `hasReceipt` was previously fetched but unused):

- `src/components/ReceiptViewDialog.vue` (new) — loads the stored image via
  `fetchListReceipt`, empty/error states, delete with confirmation via
  `deleteListReceipt`.
- `src/views/ShoppingLists.vue` — "View receipt" list action when `hasReceipt`;
  clears the flag after deletion.
- l10n `en/de/uk` (6 more strings).
- `tests/js/components/ReceiptViewDialog.spec.ts` (new) and ShoppingLists viewer
  tests; `tests/unit/Controller/ReceiptImageControllerTest.php` (new) covers the
  pre-existing upload/show/destroy endpoints.

Verification: `npm run test` (114), `lint`, `stylelint`, `build`, `l10n` pass.
Backend: `composer run lint`, `cs:check`, `test:unit` (159), `openapi` pass;
`psalm` 0 issues in new code (3 pre-existing in the unrelated LLM-profile files).
