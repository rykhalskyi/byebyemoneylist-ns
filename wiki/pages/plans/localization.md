---
created: 2026-09-11
type: plan
tags: [plan, i18n, l10n, frontend, nextcloud, testing]
related:
  - "../specs/localization.md"
  - "../tickets/ticket-15-localization-infra.md"
---

# Plan — Frontend localization (i18n)

Implementation plan for [specs/localization](../specs/localization.md). Work is
split into tickets [T15](../tickets/ticket-15-localization-infra.md) …
[T21](../tickets/ticket-21-localization-docs.md).

## Step-by-step

### T15 — Infrastructure

1. `templates/index.php`: add `Util::addTranslations(Application::APP_ID)`.
2. `package.json`: add `@nextcloud/l10n` dependency and an `l10n` script
   (`node scripts/l10n.mjs`); include `scripts` in `lint`.
3. `src/utils/l10n.ts` (new): app-bound `t()`/`n()` and a re-export of
   `getCanonicalLocale`.
4. `scripts/l10n.mjs` (new): read each `l10n/<lang>.json`
   (`{ translations, pluralForm }`) and emit `l10n/<lang>.js` with
   `OC.L10N.register("byebyemoneylist", {...}, "<pluralForm>")`, sorted keys.
5. `.l10nignore` (new): ignore `js/`, `dist/`, dependencies, tests.
6. `l10n/en.json` skeleton including `"Bye Bye Money List"`; run `npm run l10n`.

### T16 — App shell

7. `src/App.vue`: menu labels via `t()`.
8. `src/views/ShoppingLists.vue`: headings, status labels (map instead of
   capitalising), empty/error states, buttons, `Delete {name}` aria-label.
9. `src/views/Catalog.vue`: tabs, add-button label, empty states, search
   placeholders, delete title/message, error states, approve-all, per-tab search
   summary; plurals via `n()` for the pending banner and the three
   `Load more (%n remaining)` buttons.

### T17 — Dialogs

10. `NewListDialog`, `NewCategoryDialog`, `NewStoreDialog`, `NewProductDialog`,
    `AddProductDialog`, `ProductInfoDialog`, `ConfirmDialog`: dialog names,
    field labels/placeholders/helper texts, switches, buttons, error messages,
    chip labels.

### T18 — Catalog components

11. `catalog/ProductRow.vue`, `catalog/CategoryRow.vue`,
    `catalog/StoreRow.vue`: chips (`Subscription`, `Income`, `Pending Review`)
    and `Approve/Edit/Delete {name}` aria-labels.
12. `catalog/CatalogSearch.vue`: default `Search` label/placeholder and
    `Clear search`.

### T19 — Formatting & plurals

13. `src/utils/format.ts` and `ShoppingLists.vue#formatQuantity`: use
    `getCanonicalLocale()` for `Intl`/`toLocaleDateString` instead of `undefined`.
14. `Catalog.vue#compareByName`: locale-aware `localeCompare`.
15. Confirm plural coverage (`n()`), especially uk's three forms.

### T20 — Translations & checks

16. Generate `l10n/en.json` from the source inventory (identity values, plural
    arrays).
17. Author `l10n/de.json` and `l10n/uk.json`; de plural form
    `nplurals=2; plural=(n != 1);`, uk
    `nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);`.
18. `tests/js/l10n.spec.ts` (new): identical key sets, plural arrays match
    `nplurals`, and every source `t()`/`n()` string exists in `en.json`.
19. Run `npm run l10n` to compile all bundles.

### T21 — Docs

20. Wiki spec/plan/tickets; update `index.md`, `log.md`, `build-deploy.md`,
    `project-overview.md`.

## Files

New: `src/utils/l10n.ts`, `scripts/l10n.mjs`, `.l10nignore`,
`l10n/{en,de,uk}.json`, `l10n/{en,de,uk}.js` (generated),
`tests/js/l10n.spec.ts`, this wiki spec/plan and tickets T15–T21.

Modified: `templates/index.php`, `package.json`, `package-lock.json`,
`eslint.config.js`, `src/App.vue`, `src/views/{ShoppingLists,Catalog}.vue`,
`src/utils/format.ts`, `src/components/*.vue`,
`src/components/catalog/*.vue`, `wiki/index.md`, `wiki/log.md`,
`wiki/pages/build-deploy.md`, `wiki/pages/project-overview.md`.

## Migrations

None — no backend or schema change.

## Testing checklist

- [x] `npm run lint` passes (src, tests, scripts).
- [x] `npm run test` passes, including the new l10n spec (41 tests).
- [x] `npm run build` succeeds.
- [x] Compile all bundles with `npm run l10n`.
- [x] Runtime smoke test: `t()`/`n()` resolve correctly for `de` and all three
      `uk` plural forms.
- [ ] Manual: set the Nextcloud user language to German and Ukrainian; walk all
      views and dialogs; verify navigation label and date/number formats.
- [ ] `npm run stylelint`, `composer run test:unit`, `composer psalm` (no PHP
      logic touched, but confirm no regressions).

## Risks & mitigations

- **Untranslated strings creeping back in** — the coverage test scans `src/` for
  `t()`/`n()` strings and fails when `en.json` lacks a key.
- **Bundle drift between languages** — the parity test compares `en`/`de`/`uk`
  key sets and plural array lengths.
- **Wrong plural form for uk** — `pluralForm` per language; runtime smoke test
  covers counts 1/2/5.
- **`OC.L10N.register` signature** — modern core aliases `@nextcloud/l10n`'s
  `register(app, bundle)` and derives the plural function client-side, so the
  third argument is informational; verified at runtime.
- **Rollback** — revert the commit; `t()` falls back to English, so removing the
  `l10n` bundles alone degrades gracefully without breaking the UI.

## Updates

- [2026-09-11]: Created and implemented (T15–T21).
