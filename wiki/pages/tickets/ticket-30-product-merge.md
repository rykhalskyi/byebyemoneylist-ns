---
created: 2026-09-16
type: ticket
status: implemented
summary: T30 — Merge duplicate products (choose fields, concatenate aliases)
---

# T30 — Merge duplicate products

Part of the [scan-receipt epic](../epics/nextcloud-web-app.md); last item of the
source doc's **Product Catalog** section (`wiki/raw/04-scan-reciept.md`). Related:
[ticket-29-scan-receipt](ticket-29-scan-receipt.md).

## Spec

LLM receipt scans often create near-duplicate products ("Milk" vs "Milch"). The
products tab gets a **Merge** action that opens a dialog to combine two products
into one:

- Pick the duplicate to merge with (searchable list of the current tab's products).
- Choose the result **name**, **barcode**, **category**, **picture** and flags
  (favorite/subscription/income) from either product or type custom values.
- **Aliases** of both products and their original names are concatenated into one
  deduplicated list so future scans match either spelling.
- On confirm the primary product keeps its id; the secondary is deleted. All list
  items, price records, and aliases of the secondary move to the primary; the
  secondary picture is deleted or copied if chosen.

Reference: Android `ui/components/catalog/ProductMergeScreen.kt` and
`ProductMergeSearchScreen.kt`.

### Key decisions

- **Merge runs in one transaction** with alias rewrite, list-item/price re-pointing
  and the secondary delete — a partial merge would corrupt the catalog. See D-13.
- **Aliases are recomputed server-side** as the union of both products' aliases plus
  both original names, minus the chosen name (case-insensitive) — because those
  spellings are exactly what the receipt matcher should look up later.
- **Price collisions resolve to the most recent value per store** — `bbml_product_prices`
  has no unique `(product, store)` constraint, so merging naively would duplicate rows.

## Plan

Backend:

1. `ProductPictureService::copy()` — copy a picture file under another product id
   (used when the secondary picture wins).
2. `ListItemMapper::reassignProduct()` — re-point list items to the primary.
3. `ProductMergeService` (new) — transactional merge: update primary fields, rewrite
   aliases, re-point list items and prices, delete secondary, clean up pictures.
4. `ProductController::merge()` — `POST /api/products/merge` with
   `primaryId, secondaryId, name, categoryId?, barcode?, isFavorite?, isSubscription?,
   isIncome?, pictureFrom (primary|secondary|none)`; validates ownership/name/category.
5. Unit tests: `ProductMergeServiceTest`, controller merge cases.

Frontend:

6. `src/types.ts` + `src/services/listsApi.ts` — `ProductMergePayload`, `mergeProducts`.
7. `ProductMergeDialog.vue` (new) — two steps: search/select the duplicate, then
   choose fields with quick-pick buttons, picture cards and the merged alias list.
8. `ProductRow.vue` — Merge action; `Catalog.vue` wires the dialog and updates the list.
9. l10n `en/de/uk`; Vitest for `ProductRow` and `ProductMergeDialog`.

## Outcome

**Implemented (2026-09-16).**

Backend:

- `lib/Service/ProductMergeService.php` — merge transaction; alias union incl. both
  original names; price collision keeps the newest date; secondary picture copied or
  removed; old files cleaned up after commit.
- `lib/Service/ProductPictureService.php` — added `copy()`.
- `lib/Db/ListItemMapper.php` — added `reassignProduct()`.
- `lib/Controller/ProductController.php` — `POST /api/products/merge`; `openapi.json`
  regenerated (24 routes).
- `tests/unit/Service/ProductMergeServiceTest.php`, updated
  `tests/unit/Controller/ProductControllerTest.php`.

Frontend:

- `src/components/catalog/ProductMergeDialog.vue` — select + compare steps, merged
  alias chips, picture choice, live result summary.
- `src/components/catalog/ProductRow.vue` — every product now exposes a vertical
  three-dot menu (`NcActions`) with Edit, Merge and Delete; the Delete entry is
  styled with `--color-error` and the toggle stops click propagation so it does not
  open the product info dialog. `src/views/Catalog.vue` opens the merge dialog and
  swaps the merged product in place.
- `src/types.ts`, `src/services/listsApi.ts`; l10n `en/de/uk`.
- `tests/js/components/catalog/ProductMergeDialog.spec.ts`, extended
  `ProductRow.spec.ts`.

Verification: `npm run test` (124), `lint`, `stylelint`, `build`, `l10n` pass.
Backend: `composer run lint`, `cs:check`, `test:unit` (171), `openapi` pass; `psalm`
0 new issues (3 pre-existing in unrelated LLM-profile files).
