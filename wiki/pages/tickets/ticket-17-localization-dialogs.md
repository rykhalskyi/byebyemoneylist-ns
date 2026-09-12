---
created: 2026-09-11
type: ticket
tags: [ticket, i18n, l10n, frontend, ui, dialogs]
related:
  - "../epics/nextcloud-web-app.md"
  - "../specs/localization.md"
  - "../plans/localization.md"
---

# T17 — Localize dialogs

Part of [epic](../epics/nextcloud-web-app.md). Spec:
[specs/localization](../specs/localization.md). Plan:
[plans/localization](../plans/localization.md).

## Summary

Translate every string in the create/edit/info/confirm dialogs.

## Description

- `NewListDialog.vue`, `NewCategoryDialog.vue`, `NewStoreDialog.vue`,
  `NewProductDialog.vue`: dialog names, field labels, placeholders, helper
  texts, switches (`Favorite`, `Subscription`, `Income`, `Income category`),
  picture actions/hint, and Cancel/Save/Create buttons.
- `AddProductDialog.vue`: search field, empty/retry states, new-product form,
  price/quantity fields, and action buttons.
- `ProductInfoDialog.vue`: picture actions, detail labels
  (`Category`/`Barcode`/`Aliases`/`Flags`), flag chips, price-history section,
  and its delete-picture confirmation.
- `ConfirmDialog.vue`: default confirm label (`Delete`) and `Cancel`.
- All error strings (`Failed to … Please try again.`) translated.

## Design decisions

- `NcSelect`'s `label="name"` stays untouched — it selects the option field to
  render, not UI text.
- The picture-delete message keeps the literal `{name}`-free wording; the
  catalog delete confirmation uses the `{name}` placeholder.

## Files

- Modified: `src/components/NewListDialog.vue`,
  `src/components/NewCategoryDialog.vue`, `src/components/NewStoreDialog.vue`,
  `src/components/NewProductDialog.vue`, `src/components/AddProductDialog.vue`,
  `src/components/ProductInfoDialog.vue`, `src/components/ConfirmDialog.vue`.

## Acceptance criteria

- [x] No hardcoded UI text remains in the dialogs.
- [x] Lint, tests and build pass.

## Status

**Implemented (2026-09-11).**
