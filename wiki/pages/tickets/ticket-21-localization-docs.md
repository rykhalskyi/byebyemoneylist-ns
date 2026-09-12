---
created: 2026-09-11
type: ticket
tags: [ticket, i18n, l10n, docs, wiki]
related:
  - "../epics/nextcloud-web-app.md"
  - "../specs/localization.md"
  - "../plans/localization.md"
---

# T21 — Localization docs & wiki sync

Part of [epic](../epics/nextcloud-web-app.md). Spec:
[specs/localization](../specs/localization.md). Plan:
[plans/localization](../plans/localization.md).

## Summary

Document the localization work in the project wiki and update the pages that
reference build steps and architecture.

## Description

- New pages: [specs/localization](../specs/localization.md),
  [plans/localization](../plans/localization.md), and tickets T15–T21.
- `wiki/index.md`: add the spec, plan and ticket entries.
- `wiki/log.md`: append entries for the spec, plan and tickets.
- `wiki/pages/build-deploy.md`: document `npm run l10n` and the `l10n/`
  layout.
- `wiki/pages/project-overview.md`: note the localization stack
  (`@nextcloud/l10n`, `l10n/`, server-resolved language).

## Files

- New: `wiki/pages/specs/localization.md`,
  `wiki/pages/plans/localization.md`,
  `wiki/pages/tickets/ticket-15..21-localization-*.md`.
- Modified: `wiki/index.md`, `wiki/log.md`,
  `wiki/pages/build-deploy.md`, `wiki/pages/project-overview.md`.

## Acceptance criteria

- [x] Spec, plan and all seven tickets exist and cross-link.
- [x] Index and log updated.

## Status

**Implemented (2026-09-11).**
