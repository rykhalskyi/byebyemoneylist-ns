---
created: 2026-09-11
type: ticket
tags: [ticket, i18n, l10n, frontend, build, nextcloud]
related:
  - "../epics/nextcloud-web-app.md"
  - "../specs/localization.md"
  - "../plans/localization.md"
---

# T15 — L10n infrastructure & build wiring

Part of [epic](../epics/nextcloud-web-app.md). Spec:
[specs/localization](../specs/localization.md). Plan:
[plans/localization](../plans/localization.md).

## Summary

Wire the app into Nextcloud's localization stack and add the tooling needed to
author and compile translation bundles.

## Description

- `templates/index.php` now calls `Util::addTranslations(APP_ID)`, so the server
  serves `l10n/<user-language>.js` and registers it via `OC.L10N.register`.
- New `src/utils/l10n.ts`: `t(text, vars)` / `n(singular, plural, count, vars)`
  bound to `APPLICATION_ID = 'byebyemoneylist'`; re-exports
  `getCanonicalLocale`.
- New `scripts/l10n.mjs` (`npm run l10n`): reads each `l10n/<lang>.json`
  (`{ translations, pluralForm }`) and writes `l10n/<lang>.js` as
  `OC.L10N.register("byebyemoneylist", {...}, "<pluralForm>")` with sorted keys.
- New `.l10nignore` (`js/`, `dist/`, deps, tests).
- `package.json`: `@nextcloud/l10n` dependency, `l10n` script, `eslint` covers
  `scripts`; `eslint.config.js` allows `console`/no-JSDoc in CLI scripts.
- `l10n/en.json` seeded with the navigation label `"Bye Bye Money List"`.

## Design decisions

- JSON is the canonical source and is compiled to the runtime `.js`; the format
  stays compatible with Nextcloud's `translationtool`/Transifex tooling.
- One helper module keeps the app id defined in exactly one place.

## Files

- New: `src/utils/l10n.ts`, `scripts/l10n.mjs`, `.l10nignore`,
  `l10n/en.json`, `l10n/en.js`.
- Modified: `templates/index.php`, `package.json`, `package-lock.json`,
  `eslint.config.js`.

## Acceptance criteria

- [x] `Util::addTranslations` is called for the app.
- [x] `npm run l10n` compiles JSON to `.js`.
- [x] `npm run lint`, `npm run build` pass; `php -l templates/index.php` clean.

## Status

**Implemented (2026-09-11).**
