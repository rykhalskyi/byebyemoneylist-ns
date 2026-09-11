---
created: 2026-09-11
type: ticket
tags: [ticket, ui, catalog, search, fuzzy, pagination, frontend, testing]
related:
  - "../epics/nextcloud-web-app.md"
  - "../specs/catalog-search.md"
  - "../plans/catalog-search.md"
---

# T14 — Catalog: per-tab fuzzy search + paged lists

Part of [epic](../epics/nextcloud-web-app.md). Spec:
[specs/catalog-search](../specs/catalog-search.md). Plan:
[plans/catalog-search](../plans/catalog-search.md).

## Summary

Each Catalog tab now has a search field that fuzzy-filters that tab's items
(Categories, Stores, Products, Subscriptions, Income). Search runs over the whole
tab data set regardless of what is rendered, results are ranked with Fuse.js,
and long lists render with a "Load more" page size of 50. Row markup was
extracted into dedicated components and a Vitest suite was introduced.

## Description

### Search core

- `src/utils/search.ts` (new): memoizable Fuse factories for products
  (name×3, aliases×2, barcode×2), categories (name×2, parentName×1) and stores
  (name×2, address×1); `search()` returns items unchanged for an empty query.
- `src/composables/usePagedList.ts` (new): client-side windowing
  (`visible`/`hasMore`/`remaining`/`loadMore`/`reset`) that resets when the
  source array identity changes.

### Components (`src/components/catalog/`)

- `CategoryBubble.vue` — colored/emoji bubble shared by category and product rows.
- `CatalogSearch.vue` — `NcTextField type="search"` with a trailing clear button.
- `CategoryRow.vue` — `NcHighlight`ed name/parent, pending/income chips,
  approve/edit/delete; emits `confirm`/`edit`/`delete`.
- `StoreRow.vue` — name/address, accent color; emits `edit`/`delete`.
- `ProductRow.vue` — category bubble, subscription/income/price/barcode chips,
  favorite star; row click emits `open` (info dialog), edit/delete stop
  propagation.

### Catalog wiring (`src/views/Catalog.vue`)

- Search field scoped to the active tab, reset on tab switch.
- Fuse indexes held in `shallowRef` and rebuilt only on base-array change
  (load / create / edit / delete / product tab change) — not per keystroke.
- Categories render flat while searching (parent name as context); otherwise the
  existing tree.
- "Load more (N remaining)" and an `aria-live` "Showing X of Y" summary.
- Dedicated "No … found / Clear search" empty states.

### Tests

- New Vitest setup (`vitest.config.ts`, `npm run test`) with 7 spec files, 38
  tests: search matching/ranking/fuzzy, paging behaviour, and all extracted
  components (render + emitted events).

## Design decisions

- **Client-side fuzzy over a full load** — the Catalog already fetched all
  products/categories/stores, so no extra payload; true typo tolerance is not
  portably achievable in SQL across supported DBs. `search.ts` is the seam for a
  future server-side endpoint if the catalog outgrows the browser.
- **Paging is rendering-only** — search always covers every item; "Load more"
  just limits the DOM.
- **No backend change** — no API, mapper, migration or `openapi.json` update.

## Scope

- **Out:** server-side search API, search in other views, query syntax, search
  history.

## Acceptance criteria

- [x] Each tab has a search field scoped to its items.
- [x] Matching is fuzzy and typo-tolerant.
- [x] Search finds items regardless of the rendered page.
- [x] Results are ranked and rendered with "Load more" (50/page) + count.
- [x] Search resets on tab switch.
- [x] Categories show a flat filtered list while searching.
- [x] "No matches" empty state with Clear search; matched text highlighted.
- [x] `npm run test` (38 tests), `npm run lint`, `npm run stylelint`,
      `npm run build`, `composer run test:unit` (130 tests) all pass.

## Files (changed)

- New: `src/utils/search.ts`, `src/composables/usePagedList.ts`,
  `src/components/catalog/{CategoryBubble,CatalogSearch,CategoryRow,StoreRow,ProductRow}.vue`,
  `vitest.config.ts`, `tests/js/**`.
- Modified: `src/views/Catalog.vue`, `package.json`, `package-lock.json`.

## Status

**Implemented (2026-09-11).** `npm run test` 38/38, `npm run lint`,
`npm run stylelint`, `npm run build` pass; `composer run test:unit` 130/130.
Manual verification on the dev instance still recommended.
