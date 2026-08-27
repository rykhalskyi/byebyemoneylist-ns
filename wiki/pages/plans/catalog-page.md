---
created: 2026-08-27
type: plan
tags: [plan, ui, catalog, backend]
related:
  - "../specs/catalog-page.md"
  - "../tickets/ticket-02-catalog-page.md"
---

# Plan — Catalog page (T2)

Implementation plan for [specs/catalog-page](../specs/catalog-page.md) / [ticket-02](../tickets/ticket-02-catalog-page.md).

## Step-by-step

### Backend

1. **`lib/Db/CategoryMapper.php`** — add `findByIdAndOwner(string $id, string $userId): ?CategoryEntity` (id + owner scoped) for parent validation.
2. **`lib/Controller/CategoryController.php`**
   - Extract `serializeCategory(CategoryEntity): array` and reuse it in `index()`.
   - Add `create(string $name, ?string $color = null, ?string $emoji = null, ?string $parentId = null, bool $income = false)`:
     - 401 when not logged in; trim name → empty is 422.
     - Validate color regex `#[0-9a-fA-F]{6}` → 422; validate `parentId` via `findByIdAndOwner` → 422 if not owned.
     - UUID + owner via `Uuid::v4()`, set fields, `insert()`, return `['category' => ...]` 201 (500 on exception, logged).
   - OpenAPI docblock.
3. **`lib/Controller/StoreController.php`** — extract `serializeStore()`; add `create(string $name)` (trim, empty → 422, UUID + owner, 201, 500 on exception). OpenAPI docblock.

### Frontend

4. **`src/types.ts`** — add `CategoryPayload` (`name`, `color?`, `emoji?`, `parentId?`, `income?`) and `StorePayload` (`name`).
5. **`src/services/listsApi.ts`** — add `createCategory(payload)` and `createStore(payload)` (same `OcsData` unwrap pattern as `createList`).
6. **`src/views/Catalog.vue`** (new) — layout from `ShoppingLists.vue` (wrapper/header/loading/error/empty):
   - Custom tab bar (Categories | Stores) since `@nextcloud/vue` v9 has no `NcTabs`.
   - Load `Promise.all([fetchCategories(), fetchStores()])` on mount; retry on error.
   - Categories tab: build tree via children map, DFS-flatten to `{ category, depth }`, render `NcListItem` rows (emoji/color-dot icon, name, subname = parent name + income `NcChip`), indent `depth * 24px`.
   - Stores tab: flat `NcListItem` rows.
   - Header add button label switches by tab ("Add category"/"Add store").
7. **`src/components/NewCategoryDialog.vue`** (new) — `NcDialog` "New category": `NcTextField` name (required, autofocus), `NcEmojiPicker` (trigger `NcButton`, emits `select`), `NcColorPicker` (palette + clearable), `NcSelect` parent (existing categories, clearable), `NcCheckboxRadioSwitch` income. Submits `createCategory`, emits `created`.
8. **`src/components/NewStoreDialog.vue`** (new) — `NcDialog` "New store": required name field. Submits `createStore`, emits `created`.
9. **`src/App.vue`** — render `<Catalog />` for the `catalog` view (v-if / v-else-if chain).

### Tests

10. **`tests/unit/Controller/CategoryControllerTest.php`** — update constructor (LoggerInterface); add create tests: 201 + serialized fields; parent set when valid; 422 empty name; 422 invalid color; 422 unknown/foreign parent; 401.
11. **`tests/unit/Controller/StoreControllerTest.php`** — update constructor; add create tests: 201; 422 empty name; 401.

### Docs sync

12. New wiki spec/plan/ticket pages; update `wiki/index.md`, `wiki/log.md`, epic `nextcloud-web-app.md` ticket table (T4/T5 partial). Regenerate `openapi.json` via `composer openapi`.

## Files

New: `src/views/Catalog.vue`, `src/components/NewCategoryDialog.vue`, `src/components/NewStoreDialog.vue`, `wiki/pages/specs/catalog-page.md`, `wiki/pages/plans/catalog-page.md`, `wiki/pages/tickets/ticket-02-catalog-page.md`.

Modified: `lib/Controller/CategoryController.php`, `lib/Controller/StoreController.php`, `lib/Db/CategoryMapper.php`, `src/types.ts`, `src/services/listsApi.ts`, `src/App.vue`, `tests/unit/Controller/{Category,Store}ControllerTest.php`, `openapi.json`, `wiki/index.md`, `wiki/log.md`, `wiki/pages/epics/nextcloud-web-app.md`, `src/views/ShoppingLists.vue` (lint-only whitespace/indentation).

## Migrations

None — `bbml_categories`/`bbml_stores` already carry all required columns (from `Version1000Date20260826`).

## Testing checklist

- [ ] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit` pass.
- [ ] `npm run lint`, `npm run build` pass.
- [ ] `composer openapi` regenerates `openapi.json` (CI check).
- [ ] Manual: tabs switch, tree indentation, create category (emoji/color/parent/income) and store, empty/error/retry states.

## Risks & mitigations

- **`@nextcloud/vue` v9 has no `NcTabs`** → custom tab bar (small, styled to match).
- **Tree cycles** → `visited` set during DFS; server forbids a category being its own ancestor by construction at create time.
- **Pre-existing `stylelint` config error** (`stylelint-config-recommended-vue@3.2.2` incompatible with stylelint 17) — unrelated to this change; flagged for a follow-up.
- **psalm level 1** on new controller methods → typed helpers + `@psalm-suppress` for `DataResponse` generics as in T1.

## Updates

- [2026-08-27]: Created from spec. Deviations: none at plan time.
