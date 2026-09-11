---
created: 2026-09-11
type: ticket
tags: [ticket, i18n, l10n, frontend, ui]
related:
  - "../epics/nextcloud-web-app.md"
  - "../specs/localization.md"
  - "../plans/localization.md"
---

# T16 — Localize app shell

Part of [epic](../epics/nextcloud-web-app.md). Spec:
[specs/localization](../specs/localization.md). Plan:
[plans/localization](../plans/localization.md).

## Summary

Translate the app shell: navigation, Shopping Lists view, and Catalog view
chrome.

## Description

- `src/App.vue`: menu labels (`Dashboard`, `Shopping Lists`, `Analytics`,
  `Catalog`, `Settings`).
- `src/views/ShoppingLists.vue`: headings, `statusLabel` (now a
  `New`/`Finished`/`Archived` lookup instead of capitalising the status),
  empty/error states, `Add list` / `Add product`, `No items yet.`, and the
  `Delete {name}` aria-label.
- `src/views/Catalog.vue`: tab labels, add-button label, product empty states,
  search placeholders, delete title/message
  (`Delete "{name}"? This cannot be undone.`), error/empty states, approve-all,
  and per-tab search summary.
- Plurals via `n()`: the pending-review banner and all three
  `Load more (%n remaining)` buttons.

## Design decisions

- Search summary uses full sentences per tab
  (`Showing {visible} of {total} matching categories`) instead of concatenating
  a translated noun into an English frame.

## Files

- Modified: `src/App.vue`, `src/views/ShoppingLists.vue`,
  `src/views/Catalog.vue`.

## Acceptance criteria

- [x] No hardcoded UI text remains in the three files.
- [x] Plurals use `n()`; interpolated values use `{name}`/`{query}`/`{visible}`.
- [x] Existing tests still pass (English fallback).

## Status

**Implemented (2026-09-11).**
