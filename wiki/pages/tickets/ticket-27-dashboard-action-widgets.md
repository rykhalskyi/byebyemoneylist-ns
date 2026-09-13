---
created: 2026-09-13
type: ticket
status: in-progress
summary: T9.4 — Action widgets that open the Add/Scan purchase dialog on Shopping Lists
---

# T9.4 — Action widgets

Part of [epic](../epics/nextcloud-web-app.md) · [spec](../specs/dashboard.md) · [plan](../plans/dashboard.md).

## Spec

Two action widgets jump to the Shopping Lists view and open the existing
`PurchaseDialog`:

- **Add purchase** → manual tab.
- **Scan purchase** → scan tab (placeholder content preserved).

Requires a one-shot cross-view intent (selected view + dialog mode) owned by
`App.vue`, and an `initialMode` prop on `PurchaseDialog`. Depends on T9.1.

## Plan

1. `src/components/dashboard/widgets/AddPurchaseWidget.vue`,
   `ScanPurchaseWidget.vue` — emit `purchase` intent with mode `manual` / `scan`.
2. `src/App.vue` — hold the pending intent; switch to `lists`, pass it to
   `ShoppingLists`, clear after the dialog opens.
3. `src/views/ShoppingLists.vue` — accept the intent prop and open `PurchaseDialog`.
4. `src/components/PurchaseDialog.vue` — add `initialMode` used on open.
5. `l10n/{en,de,uk}.json` + `npm run l10n`; update widget type switch in `Dashboard.vue`.
6. `tests/js/views/ShoppingLists.spec.ts` / component spec for the intent.

## Outcome

Pending implementation.
