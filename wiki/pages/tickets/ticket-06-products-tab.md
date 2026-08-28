---
created: 2026-08-28
type: ticket
tags: [ticket, ui, catalog, backend, products]
related:
  - "../epics/nextcloud-web-app.md"
  - "../specs/products-tab.md"
  - "../plans/products-tab.md"
---

# T6 (partial) — Products tab in the Catalog page (display + create)

Part of [epics/nextcloud-web-app](../epics/nextcloud-web-app.md). GitHub issue: [rykhalskyi/byebyemoneylist-ns#6](https://github.com/rykhalskyi/byebyemoneylist-ns/issues/6).

## Summary

Added a **Products** tab to the Catalog page next to Categories/Stores. Products are listed (name, category, barcode chip, favorite star) and can be created (name + non-income category + barcode + aliases + favorite). Only **normal** products are returned (`is_subscription = 0 AND is_income = 0`), matching Android `ProductDao::getNormalProducts()`. Aliases live in a separate `bbml_product_aliases` table (Android `ProductAliasEntity` parity). Covers epic ticket T6 (§5.3) — display + create only.

Source: [`~/Documents/Draft/bbml-nc-ideas.md` §5.3](file:///home/admin/Documents/Draft/bbml-nc-ideas.md) and Android [`ProductScreen.kt`](https://github.com/rykhalskyi/byebyemoneylist/blob/main/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/product/ProductScreen.kt).

## Description

The Catalog page now has three tabs. The **Products** tab lists the current user's normal products sorted by name; each row shows the category name, a barcode chip when present, and a star when favorite. "Add product" opens a dialog with name (required), category select (non-income only), barcode, aliases (comma-separated) and a favorite switch.

## Requirements

- Third tab Categories | Stores | Products.
- Normal products only; category/barcode/favorite surfaced in rows.
- Create product with name/category/barcode/aliases/favorite; appears immediately.
- Loading, error-with-retry, and empty-with-CTA states.
- Server-side persistence per user.

## API design

```
GET  /apps/byebyemoneylist/api/products  → 200 { products: [{ id, name, barcode, categoryId, aliases, isFavorite, status }] }   (normal only)
POST /apps/byebyemoneylist/api/products  → 201 { product: {...} }  (body: { name, categoryId?, barcode?, aliases?, isFavorite? })
```

Validation: name required (422 empty); `categoryId` must be owned by the current user (422 otherwise) and not an income category (422); aliases trimmed/deduped.

## Design decisions

- Separate aliases table; product + aliases inserted atomically (`beginTransaction`/`commit`/`rollBack` — `IDBConnection` has no `transactional()`).
- Normal-products-only filter mirrors Android.
- Schema mirrors Android `ProductEntity` (incl. `status`, `picture_path` for future T6 work).
- Migration `Version1001Date20260827`; no changes to existing tables.

## Acceptance criteria

- [x] `POST /api/products` behaves per spec (owner-scoped, 201, validation 422s).
- [x] `GET /api/products` returns only normal products with aliases.
- [x] Products tab renders list; create flow updates the UI immediately.
- [x] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`, `npm run lint`, `npm run build`, `composer openapi` pass.

## Files (changed)

- Backend: `lib/Migration/Version1001Date20260827.php` (new), `lib/Entity/ProductEntity.php` (new), `lib/Entity/ProductAliasEntity.php` (new), `lib/Db/ProductMapper.php` (new), `lib/Db/ProductAliasMapper.php` (new), `lib/Controller/ProductController.php` (new).
- Frontend: `src/views/Catalog.vue`, `src/components/NewProductDialog.vue` (new), `src/types.ts`, `src/services/listsApi.ts`.
- Tests: `tests/unit/Controller/ProductControllerTest.php` (new).
- Docs: `openapi.json`, `wiki/index.md`, `wiki/log.md`, `wiki/pages/epics/nextcloud-web-app.md`.

## Status

**Implemented (2026-08-28).** `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit` (27 tests, 89 assertions), `npm run lint`, `npm run build`, `composer openapi` pass. Migration applied and API smoke-tested on the dev instance (`nextcloud.local`) via `curl` (GET empty, POST 201 with aliases/favorite, 422 empty name / unknown category / income category; test data cleaned up).

## Updates

- [2026-08-28]: Implemented per plan.
