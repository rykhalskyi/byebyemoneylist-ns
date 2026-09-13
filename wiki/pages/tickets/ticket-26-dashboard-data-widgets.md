---
created: 2026-09-13
type: ticket
status: implemented
summary: T9.3 — Spending widgets (today, this month, category this month)
---

# T9.3 — Data widgets

Part of [epic](../epics/nextcloud-web-app.md) · [spec](../specs/dashboard.md) · [plan](../plans/dashboard.md).

## Spec

Three read-only widgets render server-calculated totals from T9.2:

- **Spent today** — range = local day start → now.
- **Spent in month** — range = local month start → next month start.
- **Spent in category** — same month range, filtered by the widget's stored
  `categoryId`; category name/color shown on the card.

Bounds are computed in the browser's local timezone and sent as ISO instants
([D-07](../../decisions.md)). Each widget handles loading, error (with retry) and a
zero/empty state. Depends on T9.1 and T9.2.

## Plan

1. `src/utils/dashboardRange.ts` — `todayRange(now)`, `monthRange(now)` → ISO.
2. `src/components/dashboard/widgets/SpentTodayWidget.vue`.
3. `src/components/dashboard/widgets/SpentThisMonthWidget.vue`.
4. `src/components/dashboard/widgets/CategorySpendingWidget.vue` (uses stored category).
5. Wire the real widgets into `Dashboard.vue`'s type switch.
6. `l10n/{en,de,uk}.json` + `npm run l10n`.
7. `tests/js/utils/dashboardRange.spec.ts` + component specs.

## Outcome

Implemented (2026-09-13).

- `src/utils/dashboardRange.ts` computes local-time `todayRange` / `monthRange`
  (DST-safe via local date arithmetic); the ISO instants are sent to the API.
- `SpendingValue.vue` is the shared presentational loader (loading / error+retry /
  formatted amount) used by all three widgets.
- `SpentTodayWidget.vue`, `SpentThisMonthWidget.vue`, `CategorySpendingWidget.vue`
  (the latter also shows the category name) are wired into `Dashboard.vue`.
- Files: `src/utils/dashboardRange.ts`,
  `src/components/dashboard/widgets/{SpendingValue,SpentTodayWidget,SpentThisMonthWidget,CategorySpendingWidget}.vue`,
  `src/views/Dashboard.vue`, `l10n/{en,de,uk}.{json,js}`,
  `tests/js/utils/dashboardRange.spec.ts`,
  `tests/js/components/dashboard/SpendingValue.spec.ts`.
- Verified: `npm run lint`, `npm run stylelint`, `npm run test` (79),
  `npm run build`, `npm run l10n`.
