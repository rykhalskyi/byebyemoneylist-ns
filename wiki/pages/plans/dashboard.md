---
created: 2026-09-13
type: plan
status: proposed
summary: T9 Dashboard implementation plan (layout, spending API, widgets, version)
---

# Plan — Dashboard (T9)

Implementation plan for [specs/dashboard](../specs/dashboard.md). Five tickets:
[ticket-24](../tickets/ticket-24-dashboard-shell.md) →
[ticket-25](../tickets/ticket-25-dashboard-spending-api.md) →
[ticket-26](../tickets/ticket-26-dashboard-data-widgets.md) /
[ticket-27](../tickets/ticket-27-dashboard-action-widgets.md) →
[ticket-28](../tickets/ticket-28-dashboard-info-widget.md).

## Order and dependencies

```
ticket-24 (shell) ──┬── ticket-26 (data widgets) ── needs ticket-25
                    ├── ticket-27 (action widgets)
                    └── ticket-28 (info widget)

ticket-25 (spending API) is independent of ticket-24
```

## Step-by-step

### Ticket 25 — Spending API (backend first; unblocks ticket-26)

1. `lib/Db/DashboardMapper.php` (new) — `sumFinishedByRange(string $owner, string $from, string $to, ?string $categoryId): array`.
   - Base query: `bbml_lists` where `owner`, `is_finished = 1`, `is_income = 0`,
     `is_subscription = 0`, `created_at >= from`, `created_at < to`.
   - `total`: `SUM(final_total)` over the base set (null `final_total` → 0).
   - `byCategory`: left-join `bbml_list_categories` on `list_id`, group by
     `category_id`, `SUM(final_total)`.
2. `lib/Controller/DashboardController.php` (new) — `GET /api/dashboard/spending`
   (`#[ApiRoute]`, `#[NoAdminRequired]`), parse `from`/`to` (reuse the ISO parsing
   pattern from `ListController::parseDate`), validate, return
   `{ total, byCategory }`, 401 when not logged in, 422 on bad range.
3. `tests/unit/Controller/DashboardControllerTest.php` (new).
4. `composer openapi` to regenerate `openapi.json`.
5. Frontend contract: `src/types.ts` (`DashboardSpending`), `src/services/dashboardApi.ts`
   (`fetchSpending({ from, to, categoryId })`).

### Ticket 24 — Dashboard shell (client-only)

6. `src/constants/dashboardWidgets.ts` (new) — widget type ids/labels/icons and the
   `DashboardWidgetConfig { id, type, categoryId? }` type.
7. `src/composables/useDashboardWidgets.ts` (new) — load/save the array to
   `localStorage` (namespaced key), `addWidget`, `removeWidget`, `reorderWidgets`.
   Guard against malformed JSON; expose an empty array on error ([D-06](../../decisions.md)).
8. `src/components/dashboard/WidgetCard.vue` (new) — 1U card shell (title/icon,
   body slot, remove action, drag handle).
9. `src/components/dashboard/WidgetGrid.vue` (new) — responsive grid; HTML5
   drag-and-drop reorder; empty state.
10. `src/components/dashboard/AddWidgetDialog.vue` (new) — type list, category
    picker shown for `categorySpending`, disabled confirm until a category is chosen.
11. `src/views/Dashboard.vue` (new) — composes the above; renders a placeholder body
    per type until tickets 26–28 supply the real widgets.
12. `src/App.vue` — render `<Dashboard v-else-if="currentView === 'dashboard'" />`.
13. `l10n/{en,de,uk}.json` + `npm run l10n`.
14. `tests/js/composables/useDashboardWidgets.spec.ts` (new).

### Ticket 26 — Data widgets

15. `src/components/dashboard/widgets/SpentTodayWidget.vue`,
    `SpentThisMonthWidget.vue`, `CategorySpendingWidget.vue` (new); each calls
    `fetchSpending` with locally computed bounds and shows loading/error/empty.
16. `src/utils/dashboardRange.ts` (new) — `todayRange(now)`, `monthRange(now)`
    returning ISO instants from local time.
17. `tests/js/utils/dashboardRange.spec.ts` + widget tests.

### Ticket 27 — Action widgets

18. `src/components/dashboard/widgets/AddPurchaseWidget.vue`,
    `ScanPurchaseWidget.vue` (new) — emit a `purchase` intent with mode.
19. `src/App.vue` — a small cross-view intent (ref + prop/handler): switch to `lists`
    and pass the requested dialog mode down.
20. `src/views/ShoppingLists.vue` — accept an `openPurchaseMode` prop / exposed
    method; `src/components/PurchaseDialog.vue` — add an `initialMode` prop used when
    the dialog opens.

### Ticket 28 — Info widget + version

21. `lib/Controller/PageController.php` — inject `IAppManager`, pass
    `['version' => $appManager->getAppVersion(Application::APP_ID)]` to the template.
22. `templates/index.php` — add `data-version="<?php p($_['version']); ?>"` to the
    mount node.
23. `src/main.ts` — read the data attribute and `app.provide('appVersion', ...)`;
    `src/components/dashboard/widgets/InfoWidget.vue` (new) renders logo + version
    (image via `imagePath` from `@nextcloud/router`).

## Files

New:

- Backend: `lib/Db/DashboardMapper.php`, `lib/Controller/DashboardController.php`,
  `tests/unit/Controller/DashboardControllerTest.php`.
- Frontend: `src/views/Dashboard.vue`, `src/constants/dashboardWidgets.ts`,
  `src/composables/useDashboardWidgets.ts`, `src/utils/dashboardRange.ts`,
  `src/services/dashboardApi.ts`, `src/components/dashboard/{WidgetCard,WidgetGrid,AddWidgetDialog}.vue`,
  `src/components/dashboard/widgets/*.vue`,
  `tests/js/**` for the new units.

Modified:

- `src/App.vue`, `src/views/ShoppingLists.vue`, `src/components/PurchaseDialog.vue`,
  `src/types.ts`, `src/main.ts`, `lib/Controller/PageController.php`,
  `templates/index.php`, `l10n/{en,de,uk}.json`, `openapi.json`.

## Migrations

None. Widget layout is localStorage ([D-06](../../decisions.md)); the endpoint only
reads existing tables (`bbml_lists`, `bbml_list_categories`).

## Testing checklist

- [ ] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`.
- [ ] `npm run lint`, `npm run stylelint`, `npm run test`, `npm run build`,
      `npm run l10n`.
- [ ] `composer openapi` regenerates `openapi.json`.
- [ ] Manual smoke: add all six widgets, reload, remove, reorder; totals match lists;
      add/scan widgets open the dialog on the right tab; info shows the version.

## Risks & mitigations

- **Timezone drift** for "today"/"this month" — client sends explicit local `from`/`to`
  instants; the server never infers boundaries.
- **Multi-category double count** — `total` counts each list once, `byCategory` counts
  a list in every linked category; documented in the spec, revisit if a strict
  per-category total is needed.
- **Multi-category lists** make `byCategory` sums exceed `total`; same as above.
- **LocalStorage loss** is acceptable by design; malformed data must degrade to the
  empty dashboard.
- **Cross-view intent** (action widgets) must not break navigation state; keep it a
  one-shot intent cleared after the dialog opens.
- **Drag-and-drop** without a dependency: use native HTML5 DnD; keep it keyboard/
  accessible-friendly (remove is a button, not drag-only).
