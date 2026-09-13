---
created: 2026-09-13
type: ticket
status: implemented
summary: T9.2 — Server spending endpoint (range sum + by-category breakdown)
---

# T9.2 — Dashboard spending API

Part of [epic](../epics/nextcloud-web-app.md) · [spec](../specs/dashboard.md) · [plan](../plans/dashboard.md).

## Spec

One OCS endpoint computes all dashboard spending totals server-side, so the client
never downloads all lists and paging can be added later ([D-07](../../decisions.md)):

```
GET /api/dashboard/spending?from=<ISO8601>&to=<ISO8601>[&categoryId=<id>]
  → { total: float, byCategory: [ { categoryId: ?string, total: float }, ... ] }
```

- `from` inclusive, `to` exclusive; invalid range → 422; not logged in → 401.
- Sums only owner-scoped, **finished**, non-income, non-subscription lists, by
  `created_at` ([D-08](../../decisions.md)).
- `categoryId` filters `total`; `byCategory` groups via `bbml_list_categories`.
- No migration; reads existing `bbml_lists` / `bbml_list_categories`.

## Plan

1. `lib/Db/DashboardMapper.php` — `sumFinishedByRange($owner, $from, $to, $categoryId)`:
   base query on `bbml_lists` (`is_finished`, `is_income = 0`, `is_subscription = 0`,
   `created_at` range, owner) for `total`; left-join `bbml_list_categories` and group
   `category_id` for `byCategory`.
2. `lib/Controller/DashboardController.php` — `GET /api/dashboard/spending`
   (`#[ApiRoute]`, `#[NoAdminRequired]`), ISO date parsing/validation mirroring
   `ListController::parseDate`, DataResponse.
3. `tests/unit/Controller/DashboardControllerTest.php`.
4. `src/types.ts` — `DashboardSpending`; `src/services/dashboardApi.ts` —
   `fetchSpending({ from, to, categoryId })`.
5. `composer openapi`.

## Outcome

Implemented (2026-09-13).

- `DashboardMapper::sumFinishedByRange()` sums finished, non-income,
  non-subscription lists by `created_at` in `[from, to)`, owner-scoped, and
  returns `total` plus a `byCategory` breakdown (`bbml_list_categories`
  left-join). Optional category filter restricts both.
- `DashboardController` exposes `GET /api/dashboard/spending?from&to&categoryId`;
  parses/normalizes ISO-8601 to UTC, 422 on missing/invalid/inverted ranges, 401
  when not logged in.
- No migration (reads existing tables).
- Files: `lib/Db/DashboardMapper.php`, `lib/Controller/DashboardController.php`,
  `tests/unit/Controller/DashboardControllerTest.php`, `src/types.ts`,
  `src/services/dashboardApi.ts`, `openapi.json`.
- Verified: `composer lint`, `composer cs:check`, `composer psalm`,
  `composer test:unit` (136 tests, 538 assertions), `composer openapi`
  (17 routes). Note: `IQueryBuilder::PARAM_DATETIME_MUTABLE` is used because
  `PARAM_DATE` is deprecated.
