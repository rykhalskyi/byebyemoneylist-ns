---
created: 2026-08-28
type: plan
tags: [plan, ui, catalog, backend, products]
related:
  - "../specs/products-tab.md"
  - "../tickets/ticket-06-products-tab.md"
---

# Plan — Products tab (Catalog page)

Implementation plan for [specs/products-tab](../specs/products-tab.md) / [ticket-06](../tickets/ticket-06-products-tab.md).

## Step-by-step

### Backend

1. **`lib/Migration/Version1001Date20260827.php`** (new) — create `bbml_products` (id, owner, name, barcode, category_id, status default `reviewed`, picture_path, is_subscription/is_favorite/is_income booleans; owner + category indexes) and `bbml_product_aliases` (id, owner, product_id, alias_name, store_id; owner + product indexes).
2. **`lib/Entity/ProductEntity.php`** (new) — string `id` + boolean types; getters/setters for all fields (unused future-proofing getters get `@psalm-suppress PossiblyUnusedMethod`).
3. **`lib/Entity/ProductAliasEntity.php`** (new) — string `id`; owner/productId/aliasName/storeId.
4. **`lib/Db/ProductMapper.php`** (new) — `findAllByOwner($userId)` filtered to normal products (`is_subscription = 0 AND is_income = 0`, `ORDER BY name`).
5. **`lib/Db/ProductAliasMapper.php`** (new) — `findByProductIds(array $productIds, string $userId)`.
6. **`lib/Controller/ProductController.php`** (new) — injects `ProductMapper`, `ProductAliasMapper`, `CategoryMapper`, `IDBConnection`, `IUserSession`, `LoggerInterface`:
   - `index()` → 200 `{ products: [...] }` with aliases grouped per product.
   - `create(name, categoryId?, barcode?, aliases[], isFavorite?)` → validate name (422 empty), category owned + not income (422), normalize aliases; `beginTransaction` → insert product + aliases → `commit`; 201 `{ product }`, 500 on exception (rollback + log). OpenAPI docblocks.

### Frontend

7. **`src/types.ts`** — add `Product` and `ProductPayload`.
8. **`src/services/listsApi.ts`** — add `fetchProducts()` and `createProduct(payload)` (`OcsData` unwrap pattern).
9. **`src/components/NewProductDialog.vue`** (new) — `NcDialog` "New product": name (required, autofocus), `NcSelect` category (loaded via `fetchCategories`, filtered to `!income`, clearable), barcode, aliases (comma-separated), favorite switch. Submits `createProduct`, emits `created`.
10. **`src/views/Catalog.vue`** — extend tab pattern: `TabId` includes `'products'`, add tab label, load `fetchProducts()` in `Promise.all`, `addButtonLabel`/`onAdd`/`onProductCreated`, `categoryName(product)` helper; third template block (empty state + `NcListItem` rows with category subname, barcode chip, favorite star).

### Tests

11. **`tests/unit/Controller/ProductControllerTest.php`** (new) — index returns normal products with aliases / 401; create 201 + fields + alias de-dupe, sets category when valid, 422 empty name, 422 category not found, 422 income category, 401. Mocks `beginTransaction`/`commit`/`rollBack` on `IDBConnection`.

### Docs sync

12. Regenerate `openapi.json` via `composer openapi`. New wiki spec/plan/ticket pages; update `wiki/index.md`, `wiki/log.md`, epic `nextcloud-web-app.md` ticket table (T6 → partial). Run migration on dev instance (`occ migrations:migrate byebyemoneylist`).

## Files

New: `lib/Migration/Version1001Date20260827.php`, `lib/Entity/ProductEntity.php`, `lib/Entity/ProductAliasEntity.php`, `lib/Db/ProductMapper.php`, `lib/Db/ProductAliasMapper.php`, `lib/Controller/ProductController.php`, `tests/unit/Controller/ProductControllerTest.php`, `src/components/NewProductDialog.vue`, `wiki/pages/specs/products-tab.md`, `wiki/pages/plans/products-tab.md`, `wiki/pages/tickets/ticket-06-products-tab.md`.

Modified: `src/types.ts`, `src/services/listsApi.ts`, `src/views/Catalog.vue`, `openapi.json`, `wiki/index.md`, `wiki/log.md`, `wiki/pages/epics/nextcloud-web-app.md`.

## Migrations

`Version1001Date20260827` — two new tables; no changes to existing tables.

## Testing checklist

- [ ] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit` pass.
- [ ] `npm run lint`, `npm run build` pass.
- [ ] `composer openapi` regenerates `openapi.json` (CI check).
- [ ] Manual: migration applied, Products tab renders, create product (category/barcode/aliases/favorite) updates the list, empty/error/retry states, 422 validations.

## Risks & mitigations

- **No `IDBConnection::transactional()`** → explicit `beginTransaction`/`commit`/`rollBack`; tested via mocked connection.
- **psalm level 1** on new controller/entity methods → typed helpers, `array<array-key, string>`/`array<ProductAliasEntity>` params, `@psalm-suppress` for unused future-proofing getters as in T1/T2.
- **`mdiPackageVariantOff` doesn't exist in `@mdi/js` 7.4** → use `mdiPackageVariantClosed` for the empty-state icon.

## Updates

- [2026-08-28]: Created from ticket-06 (GitHub issue #6). Deviations: `IDBConnection` has no `transactional()` — used manual transaction; icon `mdiPackageVariantClosed` instead of the non-existent `mdiPackageVariantOff`.
