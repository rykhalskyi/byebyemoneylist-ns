---
created: 2026-08-28
type: spec
tags: [spec, ui, catalog, backend, products]
related:
  - "../plans/products-tab.md"
  - "../tickets/ticket-06-products-tab.md"
---

# Spec — Products tab (Catalog page)

Fourth deliverable of the web UI (see [epic](../epics/nextcloud-web-app.md), [ticket-06](../tickets/ticket-06-products-tab.md)).

Source: [`~/Documents/Draft/bbml-nc-ideas.md` §5.3 Products tab](file:///home/admin/Documents/Draft/bbml-nc-ideas.md) and the Android [`ProductScreen.kt`](https://github.com/rykhalskyi/byebyemoneylist/blob/main/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/product/ProductScreen.kt).

## Summary

Add a **Products** tab to the Catalog screen, alongside the existing **Categories** and **Stores** tabs. Products are listed (name, category, barcode chip, favorite star) and can be **created** (name + category + barcode + aliases + favorite). Only *normal* products are shown — subscription and income products are excluded (they get dedicated tabs in T7). Aliases are stored in a separate table, mirroring the Android `ProductAliasEntity`.

## Requirements

- Catalog page gains a third tab: **Categories | Stores | Products**.
- **Products tab**: flat list of the current user's normal products (`is_subscription = 0 AND is_income = 0`), sorted by name. Each row shows name; the subname shows the category name, a barcode `NcChip` (when present), and a star icon when favorite.
- **Create product**: dialog with name (required), optional category (only non-income categories of the current user), optional barcode, optional aliases (comma-separated), and a favorite switch. New product appears in the list immediately.
- Loading, error (with retry) and empty (with call-to-action) states.
- Persistence server-side per user via the app's own API.

## Scope

In scope:
- Backend: migration `Version1001Date20260827` (tables `bbml_products`, `bbml_product_aliases`), `ProductController` (`GET`/`POST /api/products`), `ProductMapper`, `ProductAliasMapper`, `ProductEntity`, `ProductAliasEntity`.
- Frontend: `Catalog.vue` third tab; `NewProductDialog.vue`; API functions `fetchProducts`/`createProduct`.
- Unit tests for the new controller methods; OpenAPI regeneration.

Out of scope (later T6/T7 work):
- Edit/delete products, picture upload, merge duplicates, price history chart, barcode scanning, review flow (status transitions).
- Subscription and income product tabs (T7).

## API design

```
GET  /apps/byebyemoneylist/api/products  → { products: [{ id, name, barcode, categoryId, aliases, isFavorite, status }] }   (normal products only)
POST /apps/byebyemoneylist/api/products  → { product: {...} }  (body: { name, categoryId?, barcode?, aliases?, isFavorite? })
```

Validation:
- `name` required, trimmed; empty → `422`.
- `categoryId` optional; must reference a category owned by the current user → `422`; the referenced category must not be an income category → `422`.
- `aliases` optional array of strings; each trimmed, empty strings and duplicates dropped.
- `isFavorite` optional, defaults to `false`.

## Design decisions

- **Separate aliases table** — `bbml_product_aliases` (owner, product_id, alias_name, store_id) matching Android `ProductAliasEntity`; product + aliases inserted atomically via `beginTransaction`/`commit`/`rollBack` on `IDBConnection` (no `transactional()` helper exists on the interface).
- **Normal products only** — the tab query filters `is_subscription = 0 AND is_income = 0`, mirroring Android `ProductDao::getNormalProducts()`.
- **Schema from the Android entity** — product columns (`name`, `barcode`, `category_id`, `status`, `picture_path`, and the three boolean flags) mirror `ProductEntity`; `picture_path`/`status` are stored for future T6 work but not surfaced in this iteration's UI.
- **Server-side UUID + owner** for products and aliases, mirroring the existing list/category/store pattern.

## UI/UX considerations

Layout follows the existing tabs: header with `<h2>Catalog</h2>` + primary add button, tab bar, then the list. Icons via `@mdi/js` (`mdiPackageVariant`, `mdiPackageVariantClosed`, `mdiStar`). Dialog uses `NcDialog` with the same pattern as the New Category dialog (autofocus, disabled-until-valid, loading on submit, inline error), `NcSelect` for category (filtered to `!income`), `NcCheckboxRadioSwitch` for favorite.

## Constraints

Same as T1/T2: Nextcloud 31–35, PHP 8.1, AppFramework conventions; Vue 3 Composition API + TypeScript + `@nextcloud/vue` + `@mdi/js`; no new runtime dependencies.

## Acceptance criteria

- [ ] `POST /api/products` creates an owner-scoped product (with aliases); rejects empty name, foreign/unknown category, and income category; returns `201`.
- [ ] `GET /api/products` returns only the current user's normal products with their aliases.
- [ ] Products tab renders the product list with category/barcode/favorite; adding a product updates the list.
- [ ] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`, `npm run lint`, `npm run build`, `composer openapi` pass.
