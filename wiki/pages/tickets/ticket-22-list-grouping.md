---
created: 2026-09-12
type: ticket
status: implemented
summary: Group shopping lists by year and month with expandable headers and finished-final-total sums
---

# T22 — Group shopping lists by year and month

## Spec

- Shopping list rows are grouped by `createdAt`: year → month → list.
- Year and month headers are collapsible; the current year and current month
  start expanded, the rest collapsed.
- Each year and month header shows, right-aligned, the sum of `finalTotal` over
  lists with `status === 'finished'` in that group. A group with no finished
  priced list shows no amount.
- Lists with a missing or invalid `createdAt` land in a single "No date" group
  sorted last.
- Confirmed with requester: sums count **only** finished lists' `finalTotal`
  (new/archived lists do not contribute). No backend/API/type changes.

## Plan

1. `src/utils/listGroups.ts` (new) — pure `groupListsByMonth` + `finishedTotal`.
2. `src/utils/format.ts` — add `formatMonth`.
3. `src/views/ShoppingLists.vue` — nested groups, collapse state, header styles.
4. `tests/js/utils/listGroups.spec.ts` (new) — grouping, ordering, sums,
   unknown-date bucket.
5. `l10n/{en,de,uk}.json` + compiled `.js` — add the "No date" string.

## Outcome

Shipped as planned. `groups` is a `computed` over `groupListsByMonth(lists)`;
`expandedYears`/`expandedMonths` record maps drive the two collapse levels and
`expandCurrentPeriod()`/`expandListGroup()` open the relevant groups on load and
after creating a list. Year and month headers span the full page width (sum
right-aligned, `box-sizing: border-box`); nested list rows stay indented via
`.month-lists`. Chevrons use `NcIconSvgWrapper inline` plus
`transform-origin: center` so they rotate in place instead of around the
default 44px icon box; mouse focus no longer draws the default ring. Header
layout is label (flex-grow, left) → sum (right) → chevron (far right), so the
full-width row no longer looks left-aligned. Nextcloud core styles raw
`button:not(.button-vue, …)` with higher specificity than CSS-module classes, so
the header needs `width/margin/padding: … !important` to actually span the page.

Files changed:

- Added `src/utils/listGroups.ts`, `tests/js/utils/listGroups.spec.ts`.
- Modified `src/utils/format.ts`, `src/views/ShoppingLists.vue`,
  `l10n/en.json`, `l10n/de.json`, `l10n/uk.json` and the compiled `l10n/*.js`.

Verification: `npm run test` (49 tests), `npm run lint` (only the pre-existing
untracked `scripts/wiki.mjs` errors remain), `npm run build`, and
`npm run stylelint` all pass.
