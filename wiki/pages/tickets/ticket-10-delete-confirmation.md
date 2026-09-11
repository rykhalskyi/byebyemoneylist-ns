---
created: 2026-09-10
type: ticket
tags: [ticket, ui, catalog, frontend, confirm-dialog, delete]
related:
  - "../epics/nextcloud-web-app.md"
---

# T10 — Catalog: confirmation dialog on deletion

Part of [epic](../epics/nextcloud-web-app.md). Source: GitHub issue #21.

## Summary

All Catalog deletions (Categories, Stores, Products, Subscriptions, Income) now go
through a reusable confirmation dialog instead of deleting on the first click. The
existing optimistic-delete behaviour is preserved: confirming removes the row
immediately and falls back to `loadData()` when the API call fails.

## Description

- New reusable `ConfirmDialog.vue` (`NcDialog` wrapper) with `open`, `title`,
  `message`, `confirmLabel`, `busy`; emits `confirm` and `update:open`.
- Message names the entity: `Delete "<name>"? This cannot be undone.` Danger-styled
  confirm button; Cancel leaves the row untouched.
- While the delete request is in flight the dialog is busy and cannot be dismissed.
- `Catalog.vue` gained a `pendingDelete` ref (`{ type, entity } | null`); each
  delete button opens the dialog, and `@confirm` runs the matching existing
  `onDelete*` handler before clearing `pendingDelete`.

## Scope

- **In:** all five Catalog tabs; shared list/delete path for the three product tabs.
- **Out:** undo / soft delete, bulk delete.

## Acceptance criteria

- [x] Deleting a category, store, product, subscription or income item always shows
  the confirmation dialog first.
- [x] Confirming removes the item; API failure rolls back via `loadData()`.
- [x] Cancelling closes the dialog with no request sent.
- [x] `npm run lint` and `npm run build` pass.

## Files (changed)

- `src/components/ConfirmDialog.vue` — new
- `src/views/Catalog.vue` — `pendingDelete`/`deleting` state, `askDelete`,
  `closeConfirmDialog`, `onConfirmDelete`, wired `ConfirmDialog`

## Status

**Implemented (2026-09-10).** `npm run lint`, `npm run stylelint`, `npm run build` pass.
