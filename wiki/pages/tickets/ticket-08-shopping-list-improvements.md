---
created: 2026-08-29
type: ticket
tags: [ticket, ui, shopping-lists, catalog, backend, categories]
related:
  - "../epics/nextcloud-web-app.md"
  - "../specs/shopping-list-improvements.md"
  - "../plans/shopping-list-improvements.md"
---

# Issue #8 — Shopping List Improvements

Part of [epic](../epics/nextcloud-web-app.md). Source: GitHub issue #8.

## Summary

Shopping Lists now show a price chip (final total, or the sum of checked items when unset) next to the status chip, with a category-color mark on each row. List items gained checkboxes (checked items feed the running total) and a delete button. The Catalog gained edit/delete on categories, stores and products (via the existing dialogs in dual create/edit mode), round category-color icon backgrounds, and a `CategoryColors` palette.

## Description

- **Lists**: `GET /api/lists` returns `totalPrice` (checked-item sum); `ShoppingLists.vue` renders a price `NcChip` = `finalTotal ?? totalPrice` and a thin category-color mark.
- **Items**: `is_checked` column; `PUT`/`DELETE /api/lists/{id}/items/{itemId}`; checkbox + delete in the item rows.
- **Catalog**: `PUT`/`DELETE` for categories/stores/products; edit/delete buttons; round color bubbles; product category icon + color mark.
- **Colors**: `src/constants/categoryColors.ts` with the requested palette; used by the category color chooser.

## Requirements

- Price chip (`finalTotal` ?? checked sum) next to status chip; checked items count; item delete.
- Category/store/product edit + delete with pre-filled dialogs.
- `CategoryColors` palette; list color marks; round category-icon backgrounds.

## API design

```
GET    /api/lists                       (adds totalPrice)
PUT    /api/lists/{id}/items/{itemId}   (isChecked?, price?, quantity?)
DELETE /api/lists/{id}/items/{itemId}
PUT    /api/categories/{id}             (name, color?, emoji?, parentId?, income?)
DELETE /api/categories/{id}
PUT    /api/stores/{id}                 (name)
DELETE /api/stores/{id}
PUT    /api/products/{id}               (name, categoryId?, barcode?, aliases?, isFavorite?)
DELETE /api/products/{id}
```

## Design decisions and scope

- `is_checked` boolean (not `status` overload); `totalPrice` computed server-side.
- Delete nulls out references (category → products/lists/children; store → lists) and removes aliases + list items for products.
- Dual-mode dialogs (optional `entity` prop).
- Out of scope: finish/purchase flow, undo-on-delete, merge, price history.

## Acceptance criteria

- [x] Migration `Version1003Date20260829` applies; all new endpoints behave per spec with owner scoping.
- [x] Price chip, color marks, item checkbox + delete, catalog edit/delete, round category icons.
- [x] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`, `npm run lint`, `npm run stylelint`, `npm run build`, `composer openapi` pass.

## Files (changed)

- Backend: `lib/Migration/Version1003Date20260829.php` (new), `lib/Entity/ListItemEntity.php`, `lib/Db/{ListItemMapper,ProductAliasMapper,StoreMapper}.php`, `lib/Controller/{List,ListItem,Category,Store,Product}Controller.php`.
- Frontend: `src/constants/categoryColors.ts` (new), `src/{types.ts,services/listsApi.ts}`, `src/views/{ShoppingLists,Catalog}.vue`, `src/components/{NewCategoryDialog,NewStoreDialog,NewProductDialog}.vue`.
- Tests: `tests/unit/Controller/{List,ListItem,Category,Store,Product}ControllerTest.php`.
- Docs: `openapi.json`, wiki spec/plan/index/log/epic.

## Status

**Implemented (2026-08-29).** Backend endpoints + migration done; `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit` (62 tests, 221 assertions), `npm run lint`, `npm run stylelint`, `npm run build`, `composer openapi` pass (verified via the dev container).
