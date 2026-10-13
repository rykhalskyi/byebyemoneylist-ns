---
created: 2026-10-13
type: ticket
status: proposed
summary: T46 — Dedicated To Buy screen (paper surface, check/add/rename/delete with undo)
---

# T46 — Dedicated To Buy screen

Frontend for the [To Buy list redesign](../specs/to-buy-list-redesign.md)
([plan](../plans/to-buy-list-redesign.md)). Needs [T43](ticket-43-to-buy-api.md);
reuses the actions from [T44](ticket-44-add-list-dialog-to-buy-card.md).

## Spec

A focused "sheet of paper" screen for the active To Buy list: own header, quick-add row,
check/uncheck, rename, delete with a 4 s undo, and empty / no-active-list states. The app
navigation is hidden while it is open (the "no bottom nav" equivalent) (R8).

## Plan

1. `src/views/ToBuy.vue` (new) — paper surface, own header, item rows, quick-add,
   empty-list hint, no-active create CTA.
2. `src/App.vue` — a `toBuy` view state that hides `Menu`; the card "Open list" action
   and the widget open it.
3. Tests: `tests/js/**` screen state spec.

## Outcome

Not started.
