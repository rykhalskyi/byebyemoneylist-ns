---
created: 2026-08-28
type: plan
tags: [plan, ui, shopping-lists, backend, items]
related:
  - "../specs/add-product-to-list.md"
  - "../tickets/ticket-07-add-product-to-list.md"
---

# Plan — Add products to a shopping list

Implementation plan for [specs/add-product-to-list](../specs/add-product-to-list.md).

## Step-by-step

### Backend

1. **Migration** — `lib/Migration/Version1002Date20260828.php`: `bbml_list_items` table (id, owner, list_id, product_id, price DECIMAL(12,2) NULL, quantity DECIMAL(12,2) default 1.0, status default `added`, created_at) with owner/list/product indexes.
2. **Entity** — `lib/Entity/ListItemEntity.php`: snake_case props, typed getters/setters, `addType` for price/quantity (DECIMAL) and createdAt (DATETIME).
3. **Mapper** — `lib/Db/ListItemMapper.php`: `findByListId(string $listId)` ASC by `created_at`.
4. **Ownership helpers** — add `findByIdAndOwner()` to `lib/Db/ListMapper.php` and `lib/Db/ProductMapper.php` (mirror `CategoryMapper::findByIdAndOwner`).
5. **Controller** — `lib/Controller/ListItemController.php` (DI `ListItemMapper`, `ListMapper`, `ProductMapper`, `IUserSession`, `LoggerInterface`):
   - `GET /api/lists/{id}/items` → 200 items (product names resolved via `findAllByOwner`), 401, 404.
   - `POST /api/lists/{id}/items` (`productId`, `price?`, `quantity?=1.0`) → 201, 401, 404 (list), 422 (product / price / quantity), 500.
   - OpenAPI docblocks; `array_values()` around serialized items for psalm list typing.
6. **Regenerate** `openapi.json` (`composer openapi`).

### Frontend

7. `src/types.ts` — `ListItem`, `ListItemPayload`.
8. `src/services/listsApi.ts` — `fetchListItems(listId)`, `addListItem(listId, payload)`.
9. `src/components/AddProductDialog.vue` (new) — search + results + create-new + price/quantity; emits `added`.
10. `src/views/ShoppingLists.vue` — `expandedId` + `itemsByList` maps, lazy `loadItems`, expandable rows, item rows (name / `quantity × price` / line total), "Add product" button.

### Tests

11. `tests/unit/Controller/ListItemControllerTest.php` — mocked mappers: index owner-scoped + product-name resolution + 401/404; create 201 with price/quantity, default quantity 1.0, 401/404/422 (product not found, quantity ≤ 0, negative price).

### Docs sync

12. Update wiki: spec, plan, ticket (`ticket-07-add-product-to-list.md`), `index.md`, `log.md`, epic ticket table (T2 partial).

## Files

New: `lib/Migration/Version1002Date20260828.php`, `lib/Entity/ListItemEntity.php`, `lib/Db/ListItemMapper.php`, `lib/Controller/ListItemController.php`, `src/components/AddProductDialog.vue`, `tests/unit/Controller/ListItemControllerTest.php`, wiki spec/plan/ticket.

Modified: `lib/Db/ListMapper.php`, `lib/Db/ProductMapper.php`, `src/types.ts`, `src/services/listsApi.ts`, `src/views/ShoppingLists.vue`, `openapi.json`, wiki `index.md` + `log.md` + epic.

## Migrations

Single migration `Version1002Date20260828` — creates `bbml_list_items`. Rollback: not supported (standard Nextcloud behavior); drop table manually in dev.

## Testing checklist

- [ ] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit` pass.
- [ ] `npm run lint`, `npm run stylelint`, `npm run build` pass.
- [ ] `composer openapi` regenerates `openapi.json`.
- [ ] Manual on dev instance: migration applies; add item with/without price and float quantity; validation 422s; items list.

## Risks & mitigations

- **psalm list typing** — use `array_values()` on serialized arrays and `@psalm-suppress` docblocks where the existing controllers do.
- **Unused entity accessors** — add `@psalm-suppress PossiblyUnusedMethod` on `getOwner`/`getStatus` (not yet read by code).
- **Mappers not unit-tested** (no DB job) — covered via controller tests with mocked mappers.

## Updates

- [2026-08-28]: Implemented. Deviation: product search is client-side (no server `search` param yet).
