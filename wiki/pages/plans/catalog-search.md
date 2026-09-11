---
created: 2026-09-11
type: plan
tags: [plan, ui, catalog, search, fuzzy, pagination, frontend, testing]
related:
  - "../specs/catalog-search.md"
  - "../tickets/ticket-14-catalog-search-paging.md"
---

# Plan — Catalog search & paged lists

Implementation plan for [specs/catalog-search](../specs/catalog-search.md) /
[ticket-14](../tickets/ticket-14-catalog-search-paging.md).

## Step-by-step

### Tooling

1. Add runtime dependency `fuse.js` and dev dependencies `vitest`,
   `@vue/test-utils`, `jsdom`, `@vitejs/plugin-vue`.
2. Add `vitest.config.ts` (standalone Vite + `@vitejs/plugin-vue`, `jsdom`,
   `tests/js/**/*.spec.ts`, `server.deps.inline: ['@nextcloud/vue']`,
   `css.modules.classNameStrategy: 'non-scoped'`).
3. `package.json` scripts: `test`, `test:watch`; extend `lint` to `tests/js`.

### Search core

4. `src/utils/search.ts` (new) — `createProductFuse` (name×3, aliases×2,
   barcode×2), `createCategoryFuse` (name×2, parentName×1), `createStoreFuse`
   (name×2, address×1); `search(fuse, items, query)` returns items unchanged for
   an empty query. Options: `threshold 0.4`, `ignoreLocation`, `includeScore`.
5. `src/composables/usePagedList.ts` (new) — `visible`, `hasMore`, `remaining`,
   `loadMore()`, `reset()`; resets when the source array identity changes.

### Component extraction — `src/components/catalog/`

6. `CategoryBubble.vue` — colored/emoji bubble (shared).
7. `CatalogSearch.vue` — search field + clear button (`v-model`).
8. `CategoryRow.vue` — category row; `edit`/`delete`/`confirm` events.
9. `StoreRow.vue` — store row (address subname, accent color); `edit`/`delete`.
10. `ProductRow.vue` — product row (category, chips, favorite); `edit`/`delete`
    and `open` (row click) events. Row-level click drives the info dialog; the
    action buttons stop propagation.

### Catalog wiring

11. `src/views/Catalog.vue` — add `query`, search field; memoized Fuse indexes
    via `shallowRef` + `watch` on `[categories, stores, activeTabProducts]`;
    filtered computeds; three `usePagedList` instances; tab-scoped placeholder;
    reset query on tab change; "Load more" + summary; flat category list while
    searching; "no matches" empty states; replace inline rows with the new
    components; remove moved styles.

### Tests — `tests/js/`

12. `utils/search.spec.ts` — empty query, substring, fuzzy typo (`zbra`),
    alias/barcode, ranking (name above alias-only).
13. `composables/usePagedList.spec.ts` — initial page, load more, short list,
    reset on source change, reset on demand.
14. `components/catalog/CatalogSearch.spec.ts` — typing emits; clear button.
15. `components/catalog/CategoryBubble.spec.ts` / `CategoryRow.spec.ts` /
    `StoreRow.spec.ts` / `ProductRow.spec.ts` — render props and emit events.

### Docs sync

16. New wiki spec/plan/ticket pages; update `index.md`, `log.md`, epic ticket
    table (T4/T5/T6).

## Files

New: `src/utils/search.ts`, `src/composables/usePagedList.ts`,
`src/components/catalog/{CategoryBubble,CatalogSearch,CategoryRow,StoreRow,ProductRow}.vue`,
`vitest.config.ts`, `tests/js/**`, `wiki/pages/specs/catalog-search.md`,
`wiki/pages/plans/catalog-search.md`,
`wiki/pages/tickets/ticket-14-catalog-search-paging.md`.

Modified: `src/views/Catalog.vue`, `package.json`, `package-lock.json`,
`wiki/index.md`, `wiki/log.md`, `wiki/pages/epics/nextcloud-web-app.md`.

## Migrations

None — no backend or schema change.

## Testing checklist

- [ ] `npm run test` (Vitest) passes.
- [ ] `npm run lint`, `npm run stylelint`, `npm run build` pass.
- [ ] `composer run test:unit` still passes (PHP unaffected).
- [ ] Manual: search each tab; fuzzy typo hit; a match from any position is
      found regardless of the rendered page; Load more; reset on tab switch;
      highlight; no-match empty state; create/edit/delete while filtered.

## Risks & mitigations

- **Index rebuild cost** — build Fuse once per base-array change (not per
  keystroke); hundreds of items is negligible.
- **Scale ceiling** — client-side suits hundreds; `search.ts` is the seam for a
  future server-side endpoint.
- **Vitest/Vite compatibility** — standalone config, pinned at install; does not
  affect the app build.
- **CSS modules in tests** — className strategy `non-scoped`; tests don't assert
  class names.

## Updates

- [2026-09-11]: Created.
