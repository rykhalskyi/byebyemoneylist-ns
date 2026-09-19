---
created: 2026-09-19
type: ticket
status: implemented
summary: T10.1 — Analytics overview API (spent/income totals + category/store/list breakdowns)
---

# T10.1 — Analytics overview API

Part of [epic](../epics/nextcloud-web-app.md). Source: `wiki/raw/05-analytics.md`.

## Spec

One read-only OCS endpoint returns all monthly figures the Analytics page needs, so
the client never downloads lists ([D-07](../../decisions.md)):

```
GET /api/analytics/overview?from=<ISO8601>&to=<ISO8601>
  → { totalSpent, totalIncome,
      byCategory: [ { categoryId: ?string, total } ],
      byStore:    [ { storeId: ?string, total } ],
      byList:     [ { listId, name, total } ] }
```

- `from` inclusive, `to` exclusive; invalid range → 422; not logged in → 401.
- Owner-scoped finished, non-income, non-subscription lists by `created_at`
  ([D-08](../../decisions.md)); `totalSpent`/`totalIncome` split by `is_income`.
- Breakdowns cover **expense** lists only. `byCategory`/`byStore`/`byList` are each
  attributed once, so segments sum to `totalSpent` ([D-15](../../decisions.md)).
- No migration; reads existing `bbml_lists` / `bbml_list_categories`.

## Plan

1. `lib/Db/AnalyticsMapper.php` — `overview($owner, $from, $to)` fetches the lists in
   range and aggregates in PHP, mapping each list to its first
   `bbml_list_categories` row (ordered by junction `id`) as its primary category.
2. `lib/Controller/AnalyticsController.php` — `GET /api/analytics/overview`
   (`#[ApiRoute]`, `#[NoAdminRequired]`), ISO parsing/validation mirroring
   `DashboardController::parseDate`.
3. `tests/unit/Controller/AnalyticsControllerTest.php`.
4. `src/types.ts` (`AnalyticsOverview`, `AnalyticsCategoryTotal`,
   `AnalyticsStoreTotal`, `AnalyticsListTotal`); `src/services/analyticsApi.ts`
   (`fetchAnalyticsOverview`).
5. `composer openapi`.

## Outcome

Implemented (2026-09-19).

- `AnalyticsMapper::overview()` returns totals plus sorted category/store/list
  breakdowns; a month with no lists short-circuits to an empty result.
- `AnalyticsController` exposes the endpoint with UTC normalization and 422/401
  errors; 5 controller tests.
- Files: `lib/Db/AnalyticsMapper.php`, `lib/Controller/AnalyticsController.php`,
  `tests/unit/Controller/AnalyticsControllerTest.php`, `src/types.ts`,
  `src/services/analyticsApi.ts`, `openapi.json`.
- Verified: `composer lint`, `composer cs:check`, `composer psalm`,
  `composer test:unit` (178 tests, 701 assertions), `composer openapi` (25 routes).
