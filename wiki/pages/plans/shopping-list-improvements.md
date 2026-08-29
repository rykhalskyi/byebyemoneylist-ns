---
created: 2026-08-29
type: plan
tags: [plan, ui, shopping-lists, catalog, backend, categories]
related:
  - "../specs/shopping-list-improvements.md"
  - "../tickets/ticket-08-shopping-list-improvements.md"
---

# Plan — Shopping List Improvements (Issue #8)

Implementation plan for [specs/shopping-list-improvements](../specs/shopping-list-improvements.md).

## Step-by-step

### Backend

1. **Migration** — `lib/Migration/Version1003Date20260829.php`: add `is_checked` BOOLEAN (notnull, default false) to `bbml_list_items`.
2. **Entity** — `lib/Entity/ListItemEntity.php`: `isChecked` property + getter/setter + `addType(BOOLEAN)`.
3. **Mappers**:
   - `lib/Db/ListItemMapper.php`: `findByIdAndListId()`, `sumCheckedByListIds()` (grouped `SUM(price*quantity)` where checked).
   - `lib/Db/ProductAliasMapper.php`: `deleteByProductId()`.
   - `lib/Db/StoreMapper.php`: `findByIdAndOwner()`.
4. **Controllers**:
   - `lib/Controller/ListItemController.php`: `PUT`/`DELETE /api/lists/{id}/items/{itemId}`; serialize `isChecked`.
   - `lib/Controller/ListController.php`: DI `ListItemMapper`; include `totalPrice` in `GET /api/lists`.
   - `lib/Controller/CategoryController.php`: `PUT`/`DELETE /api/categories/{id}` (cascade null-outs via `IDBConnection`).
   - `lib/Controller/StoreController.php`: `PUT`/`DELETE /api/stores/{id}`.
   - `lib/Controller/ProductController.php`: `PUT`/`DELETE /api/products/{id}` (alias replace / cascade).
5. **Regenerate** `openapi.json` (`composer openapi`).

### Frontend

6. `src/constants/categoryColors.ts` (new) — `CategoryColors` + `CATEGORY_COLOR_PALETTE`.
7. `src/types.ts` — `isChecked` on `ListItem`, `totalPrice` on `ShoppingList`, `ListItemUpdatePayload`.
8. `src/services/listsApi.ts` — update/delete functions for items, categories, stores, products.
9. `src/views/ShoppingLists.vue` — price chip, color mark, item checkbox + delete.
10. `src/views/Catalog.vue` — edit/delete buttons, round color bubbles, product category icon + color mark.
11. Dialogs — `NewCategoryDialog.vue` / `NewStoreDialog.vue` / `NewProductDialog.vue` dual-mode (`entity` prop), category palette from `CategoryColors`.

### Tests

12. Extend controller tests for update/delete endpoints and `totalPrice`.

### Docs sync

13. Wiki spec/plan/ticket, `index.md`, `log.md`, epic ticket table (T2/T4/T5/T6 partial status).

## Files

New: `lib/Migration/Version1003Date20260829.php`, `src/constants/categoryColors.ts`, wiki spec/plan/ticket.

Modified: `lib/Entity/ListItemEntity.php`, `lib/Db/{ListItemMapper,ProductAliasMapper,StoreMapper}.php`, `lib/Controller/{List,ListItem,Category,Store,Product}Controller.php`, `src/{types.ts,services/listsApi.ts}`, `src/views/{ShoppingLists,Catalog}.vue`, `src/components/{NewCategoryDialog,NewStoreDialog,NewProductDialog}.vue`, `openapi.json`, tests, wiki `index.md` + `log.md` + epic.

## Migrations

Single migration `Version1003Date20260829` — adds `is_checked` to `bbml_list_items`. Rollback: not supported (standard Nextcloud behavior).

## Testing checklist

- [ ] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`.
- [ ] `npm run lint`, `npm run stylelint`, `npm run build`.
- [ ] `composer openapi` regenerates `openapi.json`.
- [ ] Manual smoke test on dev instance (toggle check, delete item, edit/delete category/store/product).

## Risks & mitigations

- **OpenAPI extractor** rejects `array<empty, empty>` docblocks — use `array{}`.
- **`expr()->mul()` unavailable** — use the raw string `'price * quantity'` in `sum()`.
- **`findByIdAndOwner` on `StoreMapper`** — added to mirror `CategoryMapper` for owner-scoped update/delete.
