---
created: 2026-11-13
type: ticket
status: implemented
summary: T49 — Inbox entry moves into Shopping Lists, Inbox shows reviewable imports only, AI suggestions report profile state correctly
---

# T49 — Inbox button and AI suggestion fixes

Follow-up on the purchase import inbox and LLM profile UX. Source request:
`wiki/raw/11-bugfix-tobuylist.md` (item 2).

## Spec

- Remove Inbox from the left navigation. Add an Inbox button to the Shopping Lists
  header, before **Add purchase**, disabled when there are no incoming lists and
  labelled with their count.
- The Inbox shows only imports pending approval (`pending`/`in_review`). Approved
  and discarded imports disappear immediately after the action.
- AI suggestions must work when a profile is configured, and a conflict must not
  be misreported as a missing profile.

## Plan

1. `src/App.vue` — drop the `imports` navigation item and view branch.
2. `src/views/ShoppingLists.vue` — Inbox button with reviewable count and an
   inline `Imports` panel.
3. `src/views/Imports.vue` — filter to reviewable statuses and add a back button.
4. `src/components/imports/ImportReview.vue` — disable suggestions on imported
   lists and map `409` responses by message.
5. `src/views/Settings.vue` + `LlmProfileController::create` — selecting a profile
   keeps it active, and creating a profile with none active activates it.

## Outcome

- Inbox navigation item removed; Shopping Lists header shows `Inbox (n)`, disabled
  at zero and opening the inbox inline.
- Inbox filters out approved/discarded imports, so an imported list is gone after
  approval.
- AI suggestions distinguish “no active LLM profile” from “already imported”;
  imported reviews no longer offer suggestion actions.
- Creating the first LLM profile activates it; re-selecting an active profile no
  longer deactivates it. Import AI also falls back to the only configured profile
  when legacy data has none marked active.
- Tests: Vitest 255 green; PHPUnit `LlmProfileControllerTest`,
  `PurchaseImportControllerTest` and `PurchaseImportAiServiceTest` green; lint,
  stylelint, PHP lint and PHP CS clean; build green.
