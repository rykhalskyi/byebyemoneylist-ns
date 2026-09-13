---
created: 2026-09-13
type: spec
status: proposed
summary: T9 Dashboard — user-managed widgets (spending totals + action + info)
---

# Spec — Dashboard (T9)

Part of [epic](../epics/nextcloud-web-app.md). Source: `wiki/raw/03-dashboard.md`;
Android reference `byebyemoneylist/app/.../ui/components/dashboard/`.

## Summary

Add a **Dashboard** view to the web app: a grid of equal-sized rectangular widgets
("1U" for now — every widget has the same size). The user adds widgets from an
**Add widget** dialog, can remove them and drag-reorder them. Spending widgets show
totals calculated **server-side**; a single OCS endpoint returns the sums. Widget
layout is stored **client-side** (localStorage) for now.

## Requirements

### Shell and widget management

- The `dashboard` navigation entry (already present in `src/App.vue`) renders a
  `Dashboard.vue` view instead of the placeholder `<h2>`.
- Widgets render in a grid; all widgets are the same rectangular size (1U).
- An **Add widget** button opens an **Add widget** dialog listing the widget types.
  Choosing **Spent in category** requires picking a category in the dialog.
- Widgets can be removed and reordered by drag-and-drop.
- The layout (widget list, order, per-widget config such as the chosen category) is
  persisted per browser in `localStorage`; see [D-06](../../decisions.md).

### Widget types

| Type id | Label | Behaviour |
|---|---|---|
| `spentToday` | Spent today | Sum of finished expense lists created today |
| `categorySpending` | Spent in category | Sum of finished expense lists in the chosen category for the current month; category picked on add |
| `thisMonth` | Spent in month | Sum of finished expense lists created in the current month |
| `addPurchase` | Add purchase | Action button → open Shopping Lists and the Add purchase dialog (manual tab) |
| `scanPurchase` | Scan purchase | Action button → open Shopping Lists and the Purchase dialog on the Scan tab |
| `info` | Info | App logo + version string |

- **Spent today** / **Spent in month** / **Spent in category** are read-only totals;
  tapping a spending widget is out of scope for T9.
- **Add purchase** and **Scan purchase** reuse the existing
  [PurchaseDialog](../../../src/components/PurchaseDialog.vue): manual mode / scan tab.

### Spending semantics

- A "spent" list is one with `status = finished` (or `is_finished = true`), owned by
  the current user, with `is_income = false` and `is_subscription = false`.
- The range is determined by the list's **`created_at`** (not `purchase_date`); see
  [D-08](../../decisions.md).
- The client computes `from`/`to` in the **browser's local timezone** and sends them
  as ISO-8601 instants, so "today" and "this month" are correct regardless of the
  server timezone.
- Category totals match lists related to the category through
  `bbml_list_categories` (a list can have several categories); `total` counts each
  list once, so `sum(byCategory)` may exceed `total` when a list has multiple
  categories.

### Info widget

- Shows the app logo (`img/app.svg`) and the app version read from
  `appinfo/info.xml` via `IAppManager::getAppVersion()`; see
  [D-09](../../decisions.md).

## API design

```
GET /api/dashboard/spending?from=<ISO8601>&to=<ISO8601>[&categoryId=<id>]
  → { total: float,
      byCategory: [ { categoryId: ?string, total: float }, ... ] }
```

- `from` inclusive, `to` exclusive.
- `categoryId` optional filter; the response still includes `byCategory` so the
  client can render either a single category total or a breakdown.
- Extensible later with `groupBy=month` for the Analytics epic (T10).

## Scope

In scope:

- Frontend: Dashboard shell, widget grid, add/remove/reorder, localStorage layout,
  the six widgets, i18n (`en`/`de`/`uk`), Vitest tests.
- Backend: one OCS spending endpoint + mapper/controller + PHPUnit; `openapi.json`
  regenerated.
- Version plumbing: `PageController` → `templates/index.php` → `src/main.ts`.

Out of scope:

- Server-side storage of the widget layout (deferred; [D-06](../../decisions.md)).
- Receipt scanning implementation (the Scan tab stays a placeholder).
- Analytics charts, budgets, sharing the dashboard (T10/T11/T12).
- Widget resizing / multiple widget sizes ("1U only for now").
- Auto-refresh of totals while a widget is visible.

## Design decisions

- [D-06](../../decisions.md) — layout in localStorage, behind a composable so it can
  move to the DB later.
- [D-07](../../decisions.md) — all spending math server-side via the range endpoint.
- [D-08](../../decisions.md) — finished lists by `created_at`, excluding income and
  subscriptions.
- [D-09](../../decisions.md) — app version injected through the page template.
- Widget config persisted as a versionless JSON array `{ id, type, categoryId? }`;
  array order is the display order.

## Constraints

- Nextcloud 31–35, PHP 8.1, AppFramework/OCS; Vue 3 + TS + `@nextcloud/vue` +
  `@mdi/js`; no new runtime dependencies.
- Follow existing patterns: OCS routes via `#[ApiRoute]`, mappers via `QBMapper`,
  `t()` / `getCanonicalLocale()` for strings and formatting.
- `localStorage` is treated as disposable: losing the layout must degrade to the
  empty dashboard, never an error.

## Acceptance criteria

- [ ] Dashboard replaces the placeholder view; the nav item shows the new view.
- [ ] Add widget dialog offers all six types and requires a category for
      `categorySpending`; added widgets render at 1U.
- [ ] Widgets can be removed and drag-reordered; the layout survives a page reload.
- [ ] `GET /api/dashboard/spending` returns correct `total` / `byCategory` for
      arbitrary ranges with owner scoping and excludes income/subscriptions.
- [ ] The three spending widgets display server totals with loading/error/empty
      states; today/month use local timezone boundaries.
- [ ] Add/Scan purchase widgets open Shopping Lists with the dialog on the manual /
      scan tab respectively.
- [ ] Info widget shows the logo and the version from `info.xml`.
- [ ] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`,
      `npm run lint`, `npm run stylelint`, `npm run test`, `npm run build`,
      `npm run l10n`, `composer openapi` pass.
