---
created: 2026-09-19
type: ticket
status: implemented
summary: T10.2 — Analytics page shell, month picker, account card and drilldown category donut
---

# T10.2 — Analytics page and drilldown donut

Part of [epic](../epics/nextcloud-web-app.md). Source: `wiki/raw/05-analytics.md`;
Android reference `AnalyticsScreen.kt` / `AnalyticsCharts.kt`.

## Spec

Replace the Analytics placeholder with a monthly overview page:

- **Month picker** (`< >`) drives every element; changing month resets the drilldown.
- **Account state card**: balance (income − expenses) in large type, income and
  expenses, and the balance change vs the previous month.
- **Expenses by category donut** (Apache ECharts, [D-14](../../decisions.md)):
  - top-level categories rolled up from the list-category tree; segment color from
    the category color (palette fallback).
  - emoji-only external labels connected by lines for segments above 2%.
  - single click shows name / percent / amount in the center hole.
  - double click drills into the category's direct child categories; a **Back**
    button returns to the root chart.
  - a chip legend (color, emoji, name, percentage) under the chart.
- Charts ship in a lazy-loaded route chunk so ECharts stays out of the main bundle.

## Plan

1. `src/utils/echarts.ts` — tree-shaken `use([CanvasRenderer, PieChart, BarChart, ...])`.
2. `src/utils/analyticsRange.ts` — `MonthCursor`, `shiftMonth`, `monthRangeFor`,
   `formatMonthLabel`.
3. `src/utils/analyticsCharts.ts` — pure `buildRootSlices` / `buildChildSlices` /
   `buildDonutOption` / `buildBarOption`.
4. `src/components/analytics/{MonthPicker,AccountStateCard,CategoryDonut,ChartLegendChips}.vue`.
5. `src/views/Analytics.vue` + `src/App.vue` (`defineAsyncComponent`).
6. `l10n/{en,de,uk}.json` + `npm run l10n`; Vitest tests.

## Outcome

Implemented (2026-09-19).

- `Analytics.vue` loads the overview and the previous month in parallel, resets the
  drilldown on month change, and renders the state/empty/error branches.
- `CategoryDonut` wraps `vue-echarts`; selection lives in the component and the
  center hole is an HTML overlay (easier to test than ECharts `graphic`). Drilldown
  is two levels deep, matching Android.
- `buildRootSlices`/`buildChildSlices` are pure and unit-tested, including the
  palette fallback and uncategorized bucket.
- Added `Analytics` to the `vue/multi-word-component-names` ignores; registered
  `echarts` + `vue-echarts` as runtime dependencies.
- Files: `src/views/Analytics.vue`, `src/components/analytics/*`,
  `src/utils/{echarts,analyticsRange,analyticsCharts}.ts`, `src/App.vue`,
  `eslint.config.js`, `package.json`, `l10n/{en,de,uk}.{json,js}`,
  `tests/js/utils/analyticsCharts.spec.ts`,
  `tests/js/utils/analyticsRange.spec.ts`, `tests/js/views/Analytics.spec.ts`.
- Verified: `npm run lint`, `npm run stylelint`, `npm run test` (175 tests),
  `npm run l10n`, `npm run build` (Analytics lazy chunk).
