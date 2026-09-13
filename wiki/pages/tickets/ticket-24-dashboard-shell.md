---
created: 2026-09-13
type: ticket
status: implemented
summary: T9.1 — Dashboard shell, widget grid, add/remove/reorder with localStorage layout
---

# T9.1 — Dashboard shell (client-side layout)

Part of [epic](../epics/nextcloud-web-app.md) · [spec](../specs/dashboard.md) · [plan](../plans/dashboard.md).

## Spec

The `dashboard` navigation entry currently renders a placeholder. Replace it with a
`Dashboard.vue` grid of equal-sized (1U) widgets plus the management UI:

- **Add widget** dialog lists the six widget types; `categorySpending` requires a
  category chosen in the dialog.
- Widgets can be removed and reordered by drag-and-drop.
- The widget array (`{ id, type, categoryId? }`, order = display order) is persisted
  in `localStorage`, namespaced per app/user ([D-06](../../decisions.md)). Malformed
  stored data degrades to the empty dashboard.
- Widget bodies for data/action/info types are stubbed here and filled by T9.3/T9.4/T9.5.

Frontend only; no backend, no migration.

## Plan

1. `src/constants/dashboardWidgets.ts` — widget type ids, labels, icons, `DashboardWidgetType`,
   `DashboardWidgetConfig`.
2. `src/composables/useDashboardWidgets.ts` — `localStorage` load/save,
   `addWidget`, `removeWidget`, `reorderWidgets`.
3. `src/components/dashboard/WidgetCard.vue` — 1U card shell (icon/title, slot,
   remove, drag handle).
4. `src/components/dashboard/WidgetGrid.vue` — grid + native HTML5 DnD reorder +
   empty state.
5. `src/components/dashboard/AddWidgetDialog.vue` — type chooser + category picker.
6. `src/views/Dashboard.vue` — compose; placeholder bodies per type.
7. `src/App.vue` — render `Dashboard` for `currentView === 'dashboard'`.
8. `l10n/{en,de,uk}.json` + `npm run l10n`.
9. `tests/js/composables/useDashboardWidgets.spec.ts`.

## Outcome

Implemented (2026-09-13).

- `Dashboard.vue` renders for the existing `dashboard` nav item; `Add widget`
  opens `AddWidgetDialog` (type chooser + category picker, disabled until a
  category is chosen for `categorySpending`).
- Layout is a responsive 1U grid; widgets are removed via the card button and
  reordered with native HTML5 drag-and-drop (`WidgetGrid` emits `reorder`).
- `useDashboardWidgets` stores the array under
  `byebyemoneylist.dashboard.widgets`; malformed data and unavailable storage
  degrade to the empty dashboard.
- Widget bodies are placeholders showing the category name or “Coming soon”;
  T9.3–T9.5 replace them with real widgets.
- Added `Dashboard` to the `vue/multi-word-component-names` ignores.
- Files: `src/views/Dashboard.vue`, `src/components/dashboard/{WidgetCard,WidgetGrid,AddWidgetDialog}.vue`,
  `src/composables/useDashboardWidgets.ts`, `src/constants/dashboardWidgets.ts`,
  `src/App.vue`, `eslint.config.js`, `l10n/{en,de,uk}.{json,js}`,
  `tests/js/composables/useDashboardWidgets.spec.ts`.
- Verified: `npm run lint`, `npm run stylelint`, `npm run test` (72), `npm run build`, `npm run l10n`.
