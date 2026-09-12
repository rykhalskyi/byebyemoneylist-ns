---
created: 2026-09-11
type: ticket
tags: [ticket, i18n, l10n, frontend, ui, catalog]
related:
  - "../epics/nextcloud-web-app.md"
  - "../specs/localization.md"
  - "../plans/localization.md"
---

# T18 — Localize catalog components

Part of [epic](../epics/nextcloud-web-app.md). Spec:
[specs/localization](../specs/localization.md). Plan:
[plans/localization](../plans/localization.md).

## Summary

Translate the extracted catalog row/search components.

## Description

- `catalog/ProductRow.vue`: `Subscription` / `Income` chips and the
  `Edit {name}` / `Delete {name}` aria-labels.
- `catalog/CategoryRow.vue`: `Pending Review` / `Income` chips and the
  `Approve {name}` / `Edit {name}` / `Delete {name}` aria-labels.
- `catalog/StoreRow.vue`: the `Edit {name}` / `Delete {name}` aria-labels.
- `catalog/CatalogSearch.vue`: default `Search` label/placeholder and the
  `Clear search` trailing-button label.
- `catalog/CategoryBubble.vue`: no strings (unchanged).

## Design decisions

- Aria-labels use the same `{name}` placeholder pattern as the rest of the app,
  so they translate without changing the emitted English (keeps existing tests
  green).

## Files

- Modified: `src/components/catalog/ProductRow.vue`,
  `src/components/catalog/CategoryRow.vue`,
  `src/components/catalog/StoreRow.vue`,
  `src/components/catalog/CatalogSearch.vue`.

## Acceptance criteria

- [x] No hardcoded UI text remains in the catalog components.
- [x] Component tests still pass.

## Status

**Implemented (2026-09-11).**
