---
created: 2026-09-10
type: ticket
tags: [ticket, ui, catalog, products, prices, info-dialog, backend, frontend]
related:
  - "../epics/nextcloud-web-app.md"
  - "../tickets/ticket-13-product-pictures.md"
---

# T12 — Products: last price + info dialog with price history

Part of [epic](../epics/nextcloud-web-app.md). Source: GitHub issue #23.

## Summary

Product rows in the Products, Subscriptions and Income tabs now show the **last
price**, and clicking a row opens a **product info dialog** with the read-only
**price history**. Price storage and `GET /api/products/{id}/prices` already
existed; the web UI did not consume it.

## Description

### Backend

- `ProductPriceMapper::findLatestByProductIds(array $productIds, string $userId)` —
  latest record per product, ordered by `price_date DESC, created_at DESC`, one
  batched query (avoids N+1).
- `ProductController` injects `ProductPriceMapper`; `index()` fetches the latest
  prices for the returned products and `serializeProduct()` emits
  `lastPrice: ?float` and `lastPriceDate: ?string` (ISO-8601). Applied to all tabs.
- Constructor mock + present/absent last-price tests added to
  `ProductControllerTest`; `openapi.json` regenerated.

### Frontend

- `types.ts`: `Product` gains `lastPrice`/`lastPriceDate`; new `ProductPrice` type.
- `listsApi.ts`: `fetchProductPrices(productId)`.
- `utils/format.ts` (new): extracted `formatDate`/`formatTotal` from
  `ShoppingLists.vue`, now reused by `ShoppingLists.vue` and `Catalog.vue`.
- `Catalog.vue`: last price shown as a chip in the subname; product `NcListItem`
  click opens the dialog; Edit/Delete use `@click.stop`.
- `ProductInfoDialog.vue` (new): product details + read-only price history
  (date, value, store name resolved from the loaded stores) with
  loading/empty/error states.

## Scope

- **Out:** editing/adding/deleting prices from the web, price-history chart.

## Acceptance criteria

- [x] Product items show the last price when one exists (most recent by price date).
- [x] Clicking a product row opens an info dialog with product info and price history.
- [x] Price history shows each record's date, value and store name (when known).
- [x] Edit/Delete buttons still work without opening the info dialog.
- [x] Subscription and Income tabs behave the same.
- [x] `composer run test:unit`, `composer run openapi`, `composer run psalm`,
  `npm run lint`, `npm run build` pass.

## Files (changed)

- Backend: `lib/Db/ProductPriceMapper.php`, `lib/Controller/ProductController.php`,
  `tests/unit/Controller/ProductControllerTest.php`, `openapi.json`
- Frontend: `src/types.ts`, `src/services/listsApi.ts`, `src/utils/format.ts`
  (new), `src/views/ShoppingLists.vue`, `src/views/Catalog.vue`,
  `src/components/ProductInfoDialog.vue` (new)

## Status

**Implemented (2026-09-10).** `composer lint`, `cs:check`, `test:unit` (116 tests),
`psalm`, `openapi`; `npm run lint`, `stylelint`, `build` all pass.
