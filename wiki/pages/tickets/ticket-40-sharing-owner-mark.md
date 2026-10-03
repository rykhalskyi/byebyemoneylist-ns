---
created: 2026-10-03
type: ticket
status: implemented
summary: Owner-side shared-list mark and full share management dialog (add, edit access, revoke)
---

# T40 — Owner-side shared-list mark + share management dialog

Part of [epic](../epics/nextcloud-web-app.md) (T12). Design: [specs/list-sharing](../specs/list-sharing.md).

## Spec

Owner UX for list sharing, complementing the guest UI from
[T36](ticket-36-sharing-guest-readonly.md)/[T37](ticket-37-sharing-readwrite.md).

- An owned list that the current user has actively shared with at least one user
  shows a clickable "Shared" mark in its row.
- Clicking the mark opens the share dialog for that list.
- The dialog lists the list's shares and lets the owner:
  - add a share (user + access mode),
  - edit an active share's access mode (read only / read & write),
  - revoke an active share.
- Revoked rows stay as name-only placeholders.
- The mark appears/disappears immediately as shares change while the dialog is open.

## Plan

1. `ListController::index` — mark owned lists with an active share via
   `ListShareMapper::findActiveByOwner`; expose `hasShares` in the list payload
   (and the OpenAPI schema).
2. `ShoppingList` type + `listDisplay.hasActiveShares` helper.
3. `ShoppingListRow` — clickable `NcChip` "Shared" mark that emits `share`.
4. `ShareListDialog` — per-row `NcSelect` to edit the access mode (reuses the
   idempotent `POST /api/lists/{id}/shares` upsert); emit `sharesChanged` so the
   parent row's mark stays in sync.
5. Tests: `ListControllerTest`, `listDisplay.spec`, `ShoppingListRow.spec`,
   `ShareListDialog.spec`.

## Outcome

Implemented (2026-10-03).

- Backend: `ListController::index` collects active list ids from
  `ListShareMapper::findActiveByOwner` and serializes `hasShares` for owned lists.
  `findActiveByOwner`'s stale `@psalm-suppress PossiblyUnusedMethod` removed.
  `openapi.json` regenerated.
- Frontend: `ShoppingList.hasShares`; `hasActiveShares()` helper;
  `ShoppingListRow` renders a clickable "Shared" chip (share icon) that opens the
  dialog; `ShareListDialog` active rows use an `NcSelect` to change the mode and
  emit `sharesChanged`, which `ShoppingLists` applies to the row.
- `Failed to update the share.` added to `en`/`de`/`uk` l10n bundles.
- Verified: `composer run test:unit` (245), `psalm`, `cs:check`, `openapi`;
  `npm run test` (222), `lint`, `stylelint`, `build`.
- Follow-up (2026-10-03): the dialog is mounted with `open=true`, but the load
  watcher had no `immediate`, so shares were never fetched until an action was
  taken. The watcher now runs immediately and resets/loads on open.
- Two entry points: the row "Shared" mark opens the dialog with the share list
  (manage), while the three-dot **Share** action opens add-only mode and hides the
  list (`addOnly` prop). A Boolean `showShares` default was rejected because Vue
  casts absent Boolean props to `false`.
- Verified after follow-up: `npm run test` (224), `lint`, `stylelint`, `build`.
- Recipient validation: `ShareController::create` checks
  `IUserManager::userExists()` and returns `422` with a localized
  `User not found` (injected `IL10N`, added to en/de/uk) instead of creating an
  orphan share. The dialog surfaces the returned OCS message.
- Verified after validation: `composer run test:unit` (246), `psalm`, `cs:check`,
  `openapi`; `npm run test` (226), `lint`, `build`.
