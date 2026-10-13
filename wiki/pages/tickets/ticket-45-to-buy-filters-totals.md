---
created: 2026-10-13
type: ticket
status: proposed
summary: T45 — To Buy status filters and exclusion from totals and the purchase picker
---

# T45 — Filters & totals exclusion

Frontend/backend for the [To Buy list redesign](../specs/to-buy-list-redesign.md)
([plan](../plans/to-buy-list-redesign.md)). Needs [T43](ticket-43-to-buy-api.md).

## Spec

Add status filter chips (All / To Buy / Purchases / Income / Subscriptions) and guarantee
To Buy lists never leak into totals or the purchase "finish an existing list" picker
(R7, R10).

## Plan

1. `src/views/ShoppingLists.vue` — filter chips + kind-based filtering of grouped lists.
2. `src/components/PurchaseDialog.vue` — exclude `kind === 'NEED_TO_BUY'` from `newLists`.
3. `lib/Db/DashboardMapper.php`, `lib/Db/AnalyticsMapper.php` — add
   `kind != 'NEED_TO_BUY'` (defensive).
4. `src/utils/listGroups.ts` — verify To Buy stays out of finished totals.
5. Tests: filter logic spec; mapper tests.

## Outcome

Not started.
