---
created: 2026-08-27
type: ticket
tags: [ticket, ui, catalog, backend]
related:
  - "../epics/nextcloud-web-app.md"
  - "../specs/catalog-page.md"
  - "../plans/catalog-page.md"
---

# T2 — Catalog page (Categories + Stores tabs)

Part of [epics/nextcloud-web-app](../epics/nextcloud-web-app.md).

## Summary

Second deliverable of the web UI: a **Catalog** screen with **Categories** and **Stores** tabs. Categories are shown as a nested tree and can be created (name, emoji, color, parent, income flag); stores are listed and can be created. Covers part of epic tickets T4 (§5.1) and T5 (§5.2) — display + create only.

Source: [`~/Documents/Draft/bbml-nc-ideas.md` §5 Catalog](file:///home/admin/Documents/Draft/bbml-nc-ideas.md).

## Description

The Catalog page has two tabs. The **Categories** tab renders the current user's hierarchical categories (`parentId`) as an indented tree with emoji, color dot, name, parent in the subname and an "Income" badge. "Add category" opens a dialog with name (required), emoji picker, color picker, parent selector and an income switch. The **Stores** tab lists stores; "Add store" opens a dialog with a required name.

## Requirements

- Catalog navigation item opens the Catalog view.
- Tab bar with Categories | Stores.
- Categories as a nested tree; income categories clearly marked.
- Create category (name/emoji/color/parent/income); created category appears immediately.
- Create store (name); created store appears immediately.
- Loading, error-with-retry, and empty-with-CTA states.
- Server-side persistence per user.

## API design

```
POST /apps/byebyemoneylist/api/categories  → 201 { category: {...} }  (body: { name, color?, emoji?, parentId?, income? })
POST /apps/byebyemoneylist/api/stores      → 201 { store: {...} }     (body: { name })
```

Validation: name required (422 empty); color `#[0-9a-f]{6}` (422 invalid); `parentId` must be owned by the current user (422 otherwise).

## Design decisions

- Custom tab bar (`@nextcloud/vue` v9 has no `NcTabs`).
- Client-side tree from the flat owner-scoped category list (children map + DFS, cycle-guarded); rows indented by depth.
- Parent validated owner-scoped server-side.
- No schema change — columns already exist from T1.
- New endpoints reuse the T1 UUID + owner + serialize pattern.

## Acceptance criteria

- [ ] `POST /api/categories` and `POST /api/stores` behave per spec (owner-scoped, 201, validation 422s).
- [ ] Catalog view renders tabs, tree, and lists; empty/loading/error states work.
- [ ] Create flows update the UI immediately.
- [ ] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`, `npm run lint`, `npm run build` pass.

## Files (changed)

- Backend: `lib/Controller/CategoryController.php`, `lib/Controller/StoreController.php`, `lib/Db/CategoryMapper.php`.
- Frontend: `src/views/Catalog.vue` (new), `src/components/NewCategoryDialog.vue` (new), `src/components/NewStoreDialog.vue` (new), `src/types.ts`, `src/services/listsApi.ts`, `src/App.vue`.
- Tests: `tests/unit/Controller/{Category,Store}ControllerTest.php`.
- Docs: `openapi.json`, `wiki/index.md`, `wiki/log.md`, `wiki/pages/epics/nextcloud-web-app.md`.

## Status

**Implemented (2026-08-27).** `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit` (19 tests), `npm run lint`, `npm run build`, `composer openapi` pass on the dev instance. Also fixed pre-existing lint errors in `ShoppingLists.vue` (whitespace/indentation only). Note: `npm run stylelint` fails with a pre-existing configuration error unrelated to this change (`stylelint-config-recommended-vue@3.2.2` vs stylelint 17).

## Updates

- [2026-08-27]: Implemented per plan.
