---
created: 2026-09-20
type: ticket
status: implemented
summary: T34 — Offer to create a localized default category set when the user has none
---

# T34 — Initialize default categories

Part of [epic](../epics/nextcloud-web-app.md). Source: `wiki/raw/07-initialize-categories.md`;
Android reference `CategoryRepository.createDefaultCategories()` in the Android repo.

## Spec

- On app open, when the current user has **no categories**, show a Yes/No dialog on
  the first screen (Dashboard): “Do you want to create a default set?”.
- “Create” persists the Android default category tree and opens Catalog → Categories.
- Category names are localized to the user's Nextcloud language (`en`/`de`/`uk`, English
  fallback); the names are snapshotted at creation time as ordinary category rows.
- Scope: categories only — default products/lists from Android `createInitialData` are
  **not** created.
- Dialog is session-stable (App root); “Not now” closes it until the next page load.

## Plan

1. `src/types.ts` — `CategoryBatchItem` (batch payload with `tempId`/`status`).
2. `src/services/listsApi.ts` — `createCategoriesBatch()` → `POST /api/categories/batch`.
3. `src/constants/defaultCategories.ts` — the tree (7 roots, 26 children) plus
   `buildDefaultCategoryPayload()` resolving names with `t()`, `tempId` for parents,
   `status: 'confirmed'`.
4. `src/composables/useCategoryInitialization.ts` — `shouldPrompt()` and `initialize()`
   with a client pre-check before creating.
5. `src/components/InitializeCategoriesDialog.vue` — `NcDialog` with primary Create.
6. `src/App.vue` — prompt on mount, navigate to Catalog on success; make `Menu`
   controlled so the sidebar highlight follows.
7. `l10n/{en,de,uk}.json` — category names + dialog strings; `npm run l10n`.

## Outcome

Implemented (2026-09-20).

- No backend change was needed for the happy path: the existing transactional batch
  endpoint stores the rows scoped to the user. Ids are intentionally **not** shared
  across users ([D-17](../../decisions.md)); cross-user correlation is future link/mapping work.
- Hardened against concurrent initialization (two tabs): `POST /api/categories/batch`
  gained an `onlyIfEmpty` flag. When set, the server skips the batch if the account is
  already non-empty and derives each id deterministically (UUIDv5 of `owner:tempId`), so a
  racing request loses the primary-key race and returns the winner's rows instead of
  creating a duplicate set ([D-18](../../decisions.md)).
- Tree ported exactly from Android (`CategoryRepository.kt`): 7 roots / 26 children
  (**33** nodes — the ticket summary's “27 children” was an overcount). Colors reuse
  `src/constants/categoryColors.ts`; emojis carry the Android variation selectors.
- `de`/`uk` names seeded from the Android `values-de`/`values-uk` `strings.xml`; dialog
  strings authored.
- Tests: `tests/js/constants/defaultCategories.spec.ts`,
  `tests/js/composables/useCategoryInitialization.spec.ts`,
  `tests/js/components/InitializeCategoriesDialog.spec.ts`,
  `tests/unit/Controller/CategoryControllerTest.php` (onlyIfEmpty paths),
  `tests/unit/Util/UuidTest.php`.
- Verified: `npm run l10n`, `npm run lint`, `npm run test` (188 tests), `npm run build`,
  `composer run test:unit` (185 tests), `composer run psalm`, `composer run openapi`.
