---
created: 2026-08-28
type: ticket
tags: [ticket, ui, shopping-lists, backend, items]
related:
  - "../epics/nextcloud-web-app.md"
  - "../specs/add-product-to-list.md"
  - "../plans/add-product-to-list.md"
---

# T2 (partial) — Add products to a shopping list

Part of [epics/nextcloud-web-app](../epics/nextcloud-web-app.md).

## Summary

The Shopping Lists view can now hold **items**: each list row expands in place to show its items and an "Add product" button. The add dialog searches the catalog (name / barcode / alias), lets the user create a brand-new product (created in the catalog), then takes an optional price and a float quantity before adding. Backed by a new `bbml_list_items` table and a `ListItemController` with nested `GET`/`POST /api/lists/{id}/items` endpoints. First slice of epic ticket T2 (§2.16 review flow left for later).

## Description

- List rows in `ShoppingLists.vue` expand/collapse; items lazy-load on first expand and show `productName`, `quantity × price` and a line total.
- `AddProductDialog.vue`: search field → product results; inline "Create new product" (name → `POST /api/products`, then auto-selected); price (optional) + quantity (float, default 1.0) → "Add to list".
- Backend: `bbml_list_items` with price/quantity/status/created_at; owner-scoped list/product validation; product name resolved server-side into the item payload.

## Requirements

- Expandable list rows; lazy item loading; add flow via single dialog (Android `ProductSearchDialog` parity).
- Search catalog client-side by name/barcode/alias; create-new lands in the catalog.
- Price optional (nullable), quantity float (default 1.0, must be > 0).
- Owner-scoped endpoints; 401/404/422/500 handled; unit tests; OpenAPI regenerated.

## API design

```
GET  /apps/byebyemoneylist/api/lists/{id}/items → 200 { items: [{ id, listId, productId, productName, price, quantity, createdAt }] }
POST /apps/byebyemoneylist/api/lists/{id}/items → 201 { item: {...} }  (body: { productId, price?, quantity? })
```

## Design decisions and scope

- Nested item routes; ownership checked on the parent list; `findByIdAndOwner` added to `ListMapper` and `ProductMapper`.
- Price nullable / quantity float — Android `PurchaseItem` parity.
- Inline expandable rows instead of a separate detail page for now (matches Android `ShoppingListCard`).
- Out of scope: check/edit/delete/reorder items, review flow, purchase/finish, status transitions beyond `added`.

## Acceptance criteria

- [x] Migration `Version1002Date20260828` applies; `GET`/`POST /api/lists/{id}/items` behave per spec.
- [x] List rows expand, items lazy-load, "Add product" dialog searches + creates products + submits price/quantity.
- [x] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`, `npm run lint`, `npm run build`, `composer openapi` pass.

## Files (changed)

- Backend: `lib/Migration/Version1002Date20260828.php` (new), `lib/Entity/ListItemEntity.php` (new), `lib/Db/ListItemMapper.php` (new), `lib/Controller/ListItemController.php` (new), `lib/Db/ListMapper.php`, `lib/Db/ProductMapper.php`.
- Frontend: `src/components/AddProductDialog.vue` (new), `src/views/ShoppingLists.vue`, `src/types.ts`, `src/services/listsApi.ts`.
- Tests: `tests/unit/Controller/ListItemControllerTest.php` (new).
- Docs: `openapi.json`, wiki spec/plan/index/log/epic.

## Status

**Implemented (2026-08-28).** `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit` (37 tests, 134 assertions), `npm run lint`, `npm run build` pass; `composer openapi` regenerated (2 new paths). Migration applied on the dev instance and the API smoke-tested via `curl`: create list/product, add item with price+quantity (201), no price/default quantity (201), unknown product (422), negative quantity (422), list items (200 with `productName`); test data cleaned up.

## Updates

- [2026-08-28]: Implemented per plan.
