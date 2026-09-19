---
created: 2026-09-19
type: ticket
status: implemented
summary: T10.3 — Top 5 stores and top 5 shopping lists bar charts
---

# T10.3 — Top 5 bars

Part of [epic](../epics/nextcloud-web-app.md). Source: `wiki/raw/05-analytics.md`;
Android reference `TopListsBarChart` in `AnalyticsCharts.kt`.

## Spec

Two bar charts below the category donut, for the selected month:

- **Top 5 stores by expenses** — bars labelled with store names (null store →
  “No store”), sum shown on each bar.
- **Top 5 shopping lists by expenses** — bars labelled with list names, sum shown
  on each bar.

Both use the expense breakdown from `GET /api/analytics/overview`, taking the five
largest entries. Horizontal bars are used so long names stay readable and the
category axis doubles as the legend ([D-16](../../decisions.md)).

## Plan

1. Extend `src/utils/analyticsCharts.ts` with `BarRow` + `buildBarOption(rows)`.
2. `src/components/analytics/TopBarChart.vue` — `vue-echarts` wrapper with a height
   derived from the row count.
3. `src/views/Analytics.vue` — `storeRows` (via the store lookup) and `listRows`,
   each sorted descending and sliced to five, with an empty state.
4. Vitest coverage for `buildBarOption` and the view rows.

## Outcome

Implemented (2026-09-19).

- `buildBarOption` renders a horizontal bar series with a formatted sum label on
  each bar and palette colors; tooltips show the row name and formatted value.
- `Analytics.vue` maps `byStore` through the fetched store index and falls back to
  “No store” for `null`, and shows “No expenses this month” when a chart is empty.
- Files: `src/components/analytics/TopBarChart.vue`, `src/utils/analyticsCharts.ts`,
  `src/views/Analytics.vue`, `tests/js/utils/analyticsCharts.spec.ts`,
  `tests/js/views/Analytics.spec.ts`.
- Verified: `npm run lint`, `npm run stylelint`, `npm run test` (175 tests),
  `npm run build`.
