---
created: 2026-10-13
type: ticket
status: proposed
summary: T47 — To Buy dashboard widget (title, checked/total, pending preview, create CTA)
---

# T47 — To Buy dashboard widget

Frontend for the [To Buy list redesign](../specs/to-buy-list-redesign.md)
([plan](../plans/to-buy-list-redesign.md)). Needs [T43](ticket-43-to-buy-api.md) and
opens the screen from [T46](ticket-46-to-buy-screen.md).

## Spec

A dashboard widget showing the active To Buy list: title, `checked/total` progress and a
preview of the first up-to-3 pending item names; an empty state with a create affordance
when no active list exists. Widget layout stays in localStorage (D-32).

## Plan

1. `src/constants/dashboardWidgets.ts` — add `toBuy` type (icon/label).
2. `src/components/dashboard/widgets/ToBuyWidget.vue` (new) — title, progress, preview,
   empty-state create CTA; opens the To Buy screen.
3. `src/components/dashboard/AddWidgetDialog.vue` + `src/views/Dashboard.vue` — offer and
   render the widget; wire the `App.vue` toBuy intent.
4. Tests: `tests/js/**` widget spec.

## Outcome

Not started.
