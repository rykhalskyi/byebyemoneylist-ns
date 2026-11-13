---
created: 2026-10-13
type: ticket
status: implemented
summary: T45 — To Buy status filters and exclusion from totals and the purchase flow
---

# T45 — Filters & totals exclusion

Frontend/backend for the [To Buy list redesign](../specs/to-buy-list-redesign.md)
([plan](../plans/to-buy-list-redesign.md)). Needs [T43](ticket-43-to-buy-api.md).

## Spec

Add status filter chips (All / To Buy / Purchases / Income / Subscriptions) and guarantee
To Buy lists never leak into totals, while the purchase flow always creates a new PURCHASE
list (R7, R10).

## Plan

1. `src/views/ShoppingLists.vue` — filter chips + kind-based filtering of grouped lists.
2. `src/components/PurchaseDialog.vue` + `src/utils/purchase.ts` — purchases always create
   a new PURCHASE list; remove the `newLists` picker and `findNewListByName` open-list
   match/update path (a typed name only names the new list).
3. `lib/Db/DashboardMapper.php`, `lib/Db/AnalyticsMapper.php` — add
   `kind != 'NEED_TO_BUY'` (defensive).
4. `src/utils/listGroups.ts` — verify To Buy stays out of finished totals.
5. Tests: filter logic spec; mapper tests.

## Outcome

Implemented on `feature/to-buy-list-redesign`:

- Status filter chips All / To Buy / Purchases / Income / Subscriptions, filtering the
  grouped lists by derived kind (`matchesKindFilter`), with an empty-filter state.
- Purchase flow always creates a new PURCHASE list: removed the `newLists` picker, the
  `findNewListByName` match/update path and the `lists` prop from `PurchaseDialog`.
- `DashboardMapper` and `AnalyticsMapper` add `kind != 'NEED_TO_BUY'` defensively.
- `finishedTotal` never counts To Buy lists.
- Tests cover kind filtering, To Buy exclusion from totals and existing purchase/scan
  flows. Vitest 236 green; PHPUnit 294 green; lint/stylelint/build clean.
