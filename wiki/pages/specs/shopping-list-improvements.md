---
created: 2026-08-29
type: spec
tags: [spec, ui, shopping-lists, catalog, backend, categories]
related:
  - "../plans/shopping-list-improvements.md"
  - "../tickets/ticket-08-shopping-list-improvements.md"
---

# Spec — Shopping List Improvements (Issue #8)

Part of [epic](../epics/nextcloud-web-app.md). Source: GitHub issue #8.

## Summary

Polish and complete the **Shopping Lists** and **Catalog** views: show a list's price next to its status, add check/uncheck + delete to list items, add edit/delete to categories/stores/products, and introduce the `CategoryColors` palette with colored marks and round category icons across both views.

## Requirements

### Shopping Lists (`src/views/ShoppingLists.vue`)

- Show `finalPrice` next to the status `NcChip`; the price is also an `NcChip`.
- If `finalPrice` (stored as `finalTotal`) is not set, show the sum of the prices of **checked** products in the list.
- Each list item has a checkbox; checked items contribute their `price × quantity` to the sum.
- `finalTotal` is the actual paid price for purchased (finished) lists and may differ from the checked-item sum.
- Each list item has a delete button/icon; clicking it removes the item from the list.
- Each shopping list row has a thin color mark using the list's category color.

### Catalog (`src/views/Catalog.vue`)

- Categories, Stores and Products rows have edit and delete buttons.
- Edit opens the corresponding dialog pre-filled; the user can edit and save the entity.
- Products show a category icon when a category is assigned.
- Category rows show a round color background under the category icon.
- Product rows show a color mark when a category is assigned, with the category icon in a round background of the category color.

### Category colors

- Add a `CategoryColors` constant object with `RED #FF6B6B`, `BLUE #4D9DE0`, `GREEN #2D936C`, `YELLOW #FFD93D`, `PURPLE #9B5DE5`, `ORANGE #FF9F1C`, `TEAL #00C896`, `DEFAULT_COLOR = RED`.
- The new category dialog uses this palette in its color chooser.

## Scope

In scope:
- Backend: `is_checked` on list items; `PUT`/`DELETE` for list items, categories, stores, products; `totalPrice` (checked sum) in `GET /api/lists`.
- Frontend: price chip, color marks, item checkbox + delete, edit/delete buttons + dual-mode edit dialogs, `CategoryColors` + palette.
- Unit tests for all new endpoints; OpenAPI regenerated.

Out of scope:
- Finish/purchase flow (setting `finalTotal`/status from the UI — T3).
- Undo-on-delete, merge duplicates, price history (T4/T5/T6 later tickets).
- Recurring lists, subscriptions, analytics, settings (T7–T13).

## API design

```
GET    /api/lists                       → lists include `totalPrice` (sum of checked items' price×quantity)
PUT    /api/lists/{id}/items/{itemId}   → { item }   (body: { isChecked?, price?, quantity? })
DELETE /api/lists/{id}/items/{itemId}   → 200
PUT    /api/categories/{id}             → { category } (body: { name, color?, emoji?, parentId?, income? })
DELETE /api/categories/{id}             → 200 (nulls products.category_id, lists.category_id, re-parents children)
PUT    /api/stores/{id}                 → { store }    (body: { name })
DELETE /api/stores/{id}                 → 200 (nulls lists.store_id)
PUT    /api/products/{id}               → { product }  (body: { name, categoryId?, barcode?, aliases?, isFavorite? })
DELETE /api/products/{id}               → 200 (removes aliases + list items referencing it)
```

## Design decisions

- **Checked state** stored as a `is_checked` boolean on `bbml_list_items` (domain: "checked state" per item), not overloaded onto `status`.
- **`totalPrice` computed server-side** in `GET /api/lists` (single grouped `SUM(price×quantity)` query) so collapsed rows show the sum without loading every list's items; the UI recomputes live from loaded items after toggling.
- **Delete = null out references** (confirmed with the user): deleting a category/product/store does not block; references are nulled or (for products) related rows removed.
- **Dual-mode dialogs** (confirmed): the three `New*Dialog` components take an optional `entity` prop and switch between create/edit.
- **List color mark** uses the list's category color (confirmed); no new per-list color column.

## Constraints

- Nextcloud 31–35, PHP 8.1, AppFramework conventions; Vue 3 + TS + `@nextcloud/vue` + `@mdi/js`; no new runtime deps.

## Acceptance criteria

- [ ] `GET /api/lists` returns `totalPrice`; item toggle/delete and category/store/product edit/delete endpoints behave per spec with owner scoping.
- [ ] Shopping list rows show a price chip (`finalTotal ?? checked sum`) next to the status chip and a category color mark.
- [ ] List items can be checked (updating the sum) and deleted.
- [ ] Catalog rows have edit + delete; edit dialogs pre-fill and save; product/category icons use round category-color backgrounds.
- [ ] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`, `npm run lint`, `npm run stylelint`, `npm run build`, `composer openapi` pass.
