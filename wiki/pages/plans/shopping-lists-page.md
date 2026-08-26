---
created: 2026-08-26
type: plan
tags: [plan, ui, shopping-lists, backend]
related:
  - "../specs/shopping-lists-page.md"
  - "../tickets/ticket-01-shopping-lists-page.md"
---

# Plan — Shopping Lists page (T1)

Implementation plan for [specs/shopping-lists-page](../specs/shopping-lists-page.md) / [ticket-01](../tickets/ticket-01-shopping-lists-page.md).

## Step-by-step

### Backend

1. **Migration** — `lib/Migration/Version1000Date20260826.php` (`OCP\Migration\ISchemaMigration`). One migration creating three tables:
   - `bbml_lists`: `id` STRING(36) PK, `owner` STRING(64) + index `bbml_lists_owner_idx`, `name` STRING(255), `store_id` STRING(36) NULL, `category_id` STRING(36) NULL, `status` STRING(32) default `new`, `final_total` DECIMAL(12,2) NULL, `created_at` DATETIME.
   - `bbml_stores`: `id` STRING(36) PK, `owner` STRING(64) + index, `name` STRING(255).
   - `bbml_categories`: `id` STRING(36) PK, `owner` STRING(64) + index, `name` STRING(255), `color` STRING(7) NULL, `emoji` STRING(8) NULL, `parent_id` STRING(36) NULL, `income` BOOL default false.
2. **UUID util** — `lib/Util/Uuid.php`: static `v4(): string` from `bin2hex(random_bytes(16))` with version/variant bits set (no external dependency).
3. **Entities** — `lib/Entity/ListEntity.php`, `StoreEntity.php`, `CategoryEntity.php` (`OCP\AppFramework\Db\Entity`). snake_case protected properties matching columns, camelCase getters/setters for API fields, `addType('created_at', 'datetime')` on ListEntity.
4. **Mappers** — `lib/Db/ListMapper.php` (`findAllByOwner(string $userId): array`, `ORDER BY created_at DESC`), `StoreMapper.php` (`findAllByOwner`), `CategoryMapper.php` (`findAllByOwner`). `insert()` inherited from `QBMapper`.
5. **Controllers** — `lib/Controller/ListController.php` (`OCSController`; DI `IUserSession`, `ListMapper`, `LoggerInterface`):
   - `#[NoAdminRequired] #[ApiRoute('GET', '/lists')] index()` → `['lists' => [...]]`, filtered to current user.
   - `#[NoAdminRequired] #[ApiRoute('POST', '/lists')] create(string $name, ?string $storeId = null, ?string $categoryId = null)` → trim name; empty → `422`; else UUID + owner, insert, return `['list' => ...]` `201`.
   - `lib/Controller/StoreController.php::index()` → `['stores' => [...]]` (read-only for selectors; CRUD in T5).
   - `lib/Controller/CategoryController.php::index()` → `['categories' => [...]]` (read-only for selectors; CRUD in T4).
   - OpenAPI docblocks on all endpoints.
6. **Cleanup** — remove `lib/Controller/ApiController.php` and `tests/unit/Controller/ApiTest.php`.

### Frontend

7. `package.json` — add `@nextcloud/axios`, `@nextcloud/router` to dependencies; `npm install`.
8. `src/types.ts` — `ListStatus`, `ShoppingList`, `Store`, `Category`, `ListPayload` interfaces.
9. `src/services/listsApi.ts` — `fetchLists()`, `createList()`, `fetchStores()`, `fetchCategories()` via `@nextcloud/axios` + `generateUrl`; unwrap `data.ocs.data`.
10. `src/views/ShoppingLists.vue` — header with primary "Add list" `NcButton`; `NcListItem` rows (name / `store · date` / trailing total); `NcChip` status; `NcEmptyContent` empty state; `NcLoadingIcon` loading; error state with retry; `Intl.NumberFormat` total (no symbol until T11).
11. `src/components/NewListDialog.vue` — `NcDialog` ("New list", Create/Cancel), `NcTextField` name (required, autofocus), `NcSelect` store/category loaded from API; Create disabled until name non-empty; loading state; inline error; emits `created`.
12. `src/App.vue` — `currentView` by item id; render `<ShoppingLists />` for `lists`, placeholder for others.

### Tests

13. `tests/unit/Controller/ListControllerTest.php` — mocked `IUserSession` + `ListMapper`: index returns only current user's lists; create returns 201 + serialized list (UUID set, owner set); empty name → 422.
14. `tests/unit/Controller/StoreControllerTest.php`, `CategoryControllerTest.php` — mocked mapper: index returns owner-scoped rows.

### Docs sync

15. Update `specs/shopping-lists-page.md` + `ticket-01-shopping-lists-page.md` — FKs instead of nullable strings, read-only store/category endpoints, scope note.

## Files

New: `lib/Migration/Version1000Date20260826.php`, `lib/Util/Uuid.php`, `lib/Entity/{List,Store,Category}Entity.php`, `lib/Db/{List,Store,Category}Mapper.php`, `lib/Controller/{List,Store,Category}Controller.php`, `src/types.ts`, `src/services/listsApi.ts`, `src/views/ShoppingLists.vue`, `src/components/NewListDialog.vue`, `tests/unit/Controller/{List,Store,Category}ControllerTest.php`.

Modified: `src/App.vue`, `package.json`, `package-lock.json`, `openapi.json`, wiki spec + ticket + index + log.

Removed: `lib/Controller/ApiController.php`, `tests/unit/Controller/ApiTest.php`.

## Migrations

Single migration `Version1000Date20260826` — creates `bbml_lists`, `bbml_stores`, `bbml_categories`. Rollback: not supported (standard Nextcloud migration behavior); drop tables manually in dev.

## Testing checklist

- [ ] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit` pass.
- [ ] `npm run lint`, `npm run stylelint`, `npm run build` pass.
- [ ] `composer openapi` regenerates `openapi.json` (CI check).
- [ ] Manual: lists render, add-list dialog works from empty + populated states, new list appears on top.

## Risks & mitigations

- **psalm errorLevel 1** strictness with `Entity` magic methods → explicit typed getters/setters, `@psalm-suppress` where unavoidable.
- **OpenAPI regeneration** requires openapi-extractor vendor-bin + OCP stubs; run `composer openapi` after implementation and commit diff.
- **Mapper not unit-tested** (no DB job in CI) → kept SQL simple, behavior covered via controller tests with mocked mappers.

## Updates

- [2026-08-26]: Implemented. Deviations from plan: `doctrine/dbal` added to `require-dev`; `composer.json` scaffold bugs fixed; OCS routes registered under `/api/...`; psalm suppressions for OCP `Entity` id/getters and multi-status `DataResponse` generics.
