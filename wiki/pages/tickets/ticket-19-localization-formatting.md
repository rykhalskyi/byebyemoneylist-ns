---
created: 2026-09-11
type: ticket
tags: [ticket, i18n, l10n, frontend, formatting, plurals]
related:
  - "../epics/nextcloud-web-app.md"
  - "../specs/localization.md"
  - "../plans/localization.md"
---

# T19 — Locale-aware formatting & plurals

Part of [epic](../epics/nextcloud-web-app.md). Spec:
[specs/localization](../specs/localization.md). Plan:
[plans/localization](../plans/localization.md).

## Summary

Format dates and numbers with the user's Nextcloud locale, make sorting
locale-aware, and confirm plural handling.

## Description

- `src/utils/format.ts`: `formatDate` and `formatTotal` use
  `getCanonicalLocale()` instead of the browser-default `undefined`.
- `src/views/ShoppingLists.vue#formatQuantity`: same for
  `Intl.NumberFormat`.
- `src/views/Catalog.vue`: all sorting goes through a single
  `compareByName()` that passes `getCanonicalLocale()` to `localeCompare`.
- Plurals: `n()` is used for the pending-review banner and the three
  `Load more (%n remaining)` buttons (T16); the search summary uses full
  `t()` sentences.

## Design decisions

- `getCanonicalLocale()` (not `getLanguage()`) is used for `Intl`, because it
  returns BCP-47 (`de-DE`) as required by `Intl.NumberFormat` /
  `Date#toLocaleDateString`.

## Files

- Modified: `src/utils/format.ts`, `src/views/ShoppingLists.vue`,
  `src/views/Catalog.vue`, `src/utils/l10n.ts` (re-export).

## Acceptance criteria

- [x] No `Intl`/`toLocale*` call uses a hardcoded or default locale.
- [x] Lint, tests and build pass.

## Status

**Implemented (2026-09-11).**
