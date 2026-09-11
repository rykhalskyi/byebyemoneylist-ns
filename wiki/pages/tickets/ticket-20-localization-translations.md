---
created: 2026-09-11
type: ticket
tags: [ticket, i18n, l10n, translations, german, ukrainian, testing]
related:
  - "../epics/nextcloud-web-app.md"
  - "../specs/localization.md"
  - "../plans/localization.md"
---

# T20 — German + Ukrainian translations & parity check

Part of [epic](../epics/nextcloud-web-app.md). Spec:
[specs/localization](../specs/localization.md). Plan:
[plans/localization](../plans/localization.md).

## Summary

Ship complete `de` and `uk` translation bundles and guard them with
parity/coverage tests.

## Description

- `l10n/en.json` generated from the source inventory: 157 entries (154 singular
  strings, 2 plural pairs, the navigation label) with English values.
- `l10n/de.json` and `l10n/uk.json` provide translations for all 157 keys.
- Compile runtime bundles with `npm run l10n` →
  `l10n/{en,de,uk}.js`.
- New `tests/js/l10n.spec.ts` (3 tests):
  - identical key sets across `en`/`de`/`uk`;
  - plural entries are arrays whose length matches `nplurals` in `pluralForm`;
  - every `t()`/`n()` string found in `src/` exists in `en.json`.

## Design decisions

- Plural forms: `de` `nplurals=2; plural=(n != 1);`; `uk` `nplurals=3;` with the
  standard Ukrainian rule. The runtime resolves plurals client-side via
  `@nextcloud/l10n`'s `getPlural`, so `uk` counts 1/2/5 map to forms 0/1/2.
- English values are the source strings themselves (identity), so the app still
  works if only `en` is present.

## Verification

Runtime smoke test loading the compiled bundles: `de` resolves `Catalog`,
`Delete {name}`, and plurals; `uk` resolves `Catalog`, search text, and all
three plural forms (1 → `категорію`, 2 → `категорії`, 5 → `категорій`).

## Files

- New: `l10n/{en,de,uk}.json`, `l10n/{en,de,uk}.js`,
  `tests/js/l10n.spec.ts`.

## Acceptance criteria

- [x] All three bundles have identical key sets.
- [x] Plural arrays match each language's `nplurals`.
- [x] Every source string is covered.
- [x] `npm run test` passes (41 tests); bundles valid JS
      (`node --check`).

## Status

**Implemented (2026-09-11).**
