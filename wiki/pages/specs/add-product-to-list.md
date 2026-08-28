---
created: 2026-08-28
type: spec
tags: [spec, ui, shopping-lists, backend, items]
related:
  - "../plans/add-product-to-list.md"
  - "../tickets/ticket-07-add-product-to-list.md"
---

# Spec — Add products to a shopping list

First step of epic ticket T2 (List detail — items) for the web UI. See [epic](../epics/nextcloud-web-app.md).

Source: Android [`ProductSearchDialog.kt`](https://github.com/rykhalskyi/byebyemoneylist/blob/main/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/product/ProductSearchDialog.kt) and [`ShoppingListCard.kt`](https://github.com/rykhalskyi/byebyemoneylist/blob/main/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/shoppinglist/ShoppingListCard.kt).

## Summary

Users can add **products to a shopping list** from the Shopping Lists view. Each list row expands in place to show its items and an "Add product" button. The add flow is a single dialog: search the user's catalog (name / barcode / alias), pick a product, or create a brand-new product (which lands in the catalog), then enter an optional price and a float quantity and add it to the list. Items are persisted server-side per list.

## Requirements

- Each list row is expandable in place (no separate detail page yet); expanding lazy-loads that list's items.
- Items render with product name, `quantity × price` and a line total (`price × quantity`, empty when no price).
- "Add product" button on every expanded list opens the add dialog.
- Add dialog (single dialog, mirrors Android `ProductSearchDialog`):
  - Search field filters catalog products by name, barcode and aliases (client-side).
  - Clicking a result selects it.
  - "Create new product" section: name field → creates the product in the catalog via the existing `POST /api/products` endpoint, then auto-selects it.
  - Once a product is selected: **price** (optional, nullable — Android `PurchaseItem.price` parity) and **quantity** (float, default `1.0`) are entered.
  - "Add to list" persists the item and appends it to the expanded items.
- Added item shows immediately without a full reload.

## Scope

In scope:
- Backend: `bbml_list_items` table + entity + mapper; `ListItemController` with `GET /api/lists/{id}/items` and `POST /api/lists/{id}/items` (owner-scoped); `findByIdAndOwner` on `ListMapper`/`ProductMapper`; migration `Version1002Date20260828`; unit tests.
- Frontend: expandable rows + items + "Add product" in `ShoppingLists.vue`; new `AddProductDialog.vue`; `ListItem`/`ListItemPayload` types; `fetchListItems`/`addListItem` API functions.

Out of scope (later T2/T3 work): editing/checking/deleting/reordering items, review flow, finishing/purchasing a list, `status` transitions beyond `added`, sharing.

## Data model

`bbml_list_items` table:

| Column | Type | Notes |
|--------|------|-------|
| `id` | string (UUID) | Server-generated at creation |
| `owner` | string (user id) | Creating user; owner-only access enforced in controller |
| `list_id` | string (UUID) | FK to `bbml_lists.id`; nested route owns the list |
| `product_id` | string (UUID) | FK to `bbml_products.id`; must belong to the same user |
| `price` | decimal(12,2)/null | Optional; must not be negative |
| `quantity` | decimal(12,2) | Float quantity, default `1.0`, must be > 0 |
| `status` | string | `added` at creation (check/review flows later) |
| `created_at` | datetime | Server time, ASC ordering for display |

## API design

```
GET  /apps/byebyemoneylist/api/lists/{id}/items → 200 { items: [{ id, listId, productId, productName, price, quantity, createdAt }] }
POST /apps/byebyemoneylist/api/lists/{id}/items → 201 { item: {...} }  (body: { productId, price?, quantity? })
```

Validation: `{id}` must reference a list owned by the current user (404 otherwise); `productId` must be owned by the current user (422 otherwise); `price ≥ 0` when given (422 otherwise); `quantity > 0` (422 otherwise, default `1.0`). `productName` is resolved server-side so the frontend needs no join.

## Design decisions

- **Nested item routes on lists** (`/api/lists/{id}/items`) — items are a sub-resource; ownership checked on the parent list.
- **Product name denormalized into the item response** — the serializer resolves names from the user's products so the UI renders instantly.
- **Price nullable, quantity float** — parity with Android `PurchaseItem` (`price: Double?`, `quantity: Double = 1.0`).
- **Inline expandable rows** (not a separate detail page yet) — matches Android `ShoppingListCard` expand behavior and keeps T2 incremental.
- **Single add dialog** — search + create-new + price/quantity in one `NcDialog`, matching `ProductSearchDialog` UX.

## UI/UX considerations

- Follows the Nextcloud design system: `NcDialog`, `NcListItem`, `NcTextField`, `NcButton`, `NcLoadingIcon`.
- Loading, empty-catalog, no-search-results, inline error and disabled-submit states in the dialog.
- Price/quantity inputs use `inputmode="decimal"`; quantity shows error when ≤ 0, price when < 0.
- Expanded section shows a compact item list; "No items yet." placeholder before the first add.
- Quantity formats as integer when whole (`1`), else up to 2 decimals (`1.5`).

## Constraints

- Nextcloud 31–35, PHP 8.1, AppFramework conventions; Vue 3 `<script setup>` + TypeScript + `@nextcloud/vue`.
- Runtime deps unchanged (`@nextcloud/axios`, `@nextcloud/router`).
- Product search is client-side over `GET /api/products` (catalog scale is small); a server `search` param is a later optimization.

## Acceptance criteria

- [ ] Migration creates `bbml_list_items` cleanly; no changes to existing tables.
- [ ] `GET /api/lists/{id}/items` returns only the current user's list items, newest-last, with `productName`; 401 / 404 handled.
- [ ] `POST /api/lists/{id}/items` adds an item (201) with price/quantity validation and ownership checks.
- [ ] List rows expand/collapse; items lazy-load; "Add product" opens the dialog.
- [ ] Add dialog searches, creates new products (landing in the catalog), and submits price + quantity.
- [ ] `npm run lint`, `npm run build`, `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`, `composer openapi` pass.

## Updates

- [2026-08-28]: Created.
