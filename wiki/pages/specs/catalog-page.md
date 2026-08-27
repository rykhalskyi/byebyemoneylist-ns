---
created: 2026-08-27
type: spec
tags: [spec, ui, catalog, backend]
related:
  - "../plans/catalog-page.md"
  - "../tickets/ticket-02-catalog-page.md"
---

# Spec — Catalog page (Categories + Stores tabs)

Second deliverable of the web UI (see [epic](../epics/nextcloud-web-app.md), [ticket](../tickets/ticket-02-catalog-page.md)).

Source: [`~/Documents/Draft/bbml-nc-ideas.md` §5 Catalog](file:///home/admin/Documents/Draft/bbml-nc-ideas.md).

## Summary

A **Catalog** screen with two tabs — **Categories** and **Stores**. Categories are shown as a nested tree (hierarchical via `parentId`, with color, emoji and an income flag) and can be **created**. Stores are listed and can be **created**. Edit/delete of both and the Products/Subscriptions/Income tabs land in later tickets.

## Requirements

- Catalog navigation item opens the new view with a tab bar: **Categories** | **Stores**.
- **Categories tab**: show all of the current user's categories as a hierarchy (children indented under their parent). Each row shows emoji, color dot and name; the subname shows the parent name and an "Income" badge for income categories.
- **Create category**: dialog with name (required), optional emoji, optional color, optional parent (any existing category of the same user), and an income switch. New category appears in the tree immediately.
- **Stores tab**: flat list of the current user's stores.
- **Create store**: dialog with name (required). New store appears in the list immediately.
- Loading, error (with retry) and empty (with call-to-action) states for both tabs.
- Persistence server-side per user via the app's own API.

## Scope

In scope:
- Backend: `POST /api/categories` (`CategoryController::create`), `POST /api/stores` (`StoreController::create`); `CategoryMapper::findByIdAndOwner` for parent validation.
- Frontend: `Catalog.vue` view wired into `App.vue`; `NewCategoryDialog.vue`, `NewStoreDialog.vue`; API functions `createCategory`/`createStore`.
- Unit tests for the new controller methods.

Out of scope (later tickets):
- Edit/delete categories and stores (incl. delete-with-undo, merge duplicates — T4/T5 §5.1/§5.2).
- Products tab (T6), Subscriptions & Income tabs (T7), price history (T6).
- Category/store selectors in other views (already exist read-only for lists).

## API design

```
GET  /apps/byebyemoneylist/api/categories  → { categories: [{ id, name, color, emoji, parentId, income }] }   (existing)
POST /apps/byebyemoneylist/api/categories  → { category: {...} }   (body: { name, color?, emoji?, parentId?, income? })
GET  /apps/byebyemoneylist/api/stores      → { stores: [{ id, name }] }                                        (existing)
POST /apps/byebyemoneylist/api/stores      → { store: {...} }       (body: { name })
```

Validation:
- `name` required, trimmed; empty → `422`.
- `color` optional; must match `#[0-9a-fA-F]{6}` when present → else `422`.
- `parentId` optional; must reference a category owned by the current user → else `422` (prevents cross-user references).
- `income` optional, defaults to `false`.

## Design decisions

- **Flat list + depth from parent chain** on the frontend: categories are stored as a flat owner-scoped list; the tree is built client-side (children map, DFS) and rendered as indented rows (depth × 24px). Recursion guards against accidental cycles.
- **Custom tab bar** — `@nextcloud/vue` v9 has no general-purpose tabs component (`NcTabs`); a small custom tab bar with active underline matches the existing visual style.
- **Parent must belong to the current user** — enforced server-side at create time (owner-scoped lookup), keeping the hierarchy per-user.
- **No schema change** — `bbml_categories` (color, emoji, parent_id, income) and `bbml_stores` already carry all needed columns from T1.
- **Server-side UUID + owner** for both new endpoints, mirroring the existing list-create pattern.

## UI/UX considerations

Layout follows the existing views: header with `<h2>Catalog</h2>` and a primary add button, then a tab bar, then the list. Icons via `@mdi/js` (`mdiTagMultiple`, `mdiStore`). Dialogs use `NcDialog` with the same pattern as the New List dialog (autofocus, disabled-until-valid, loading on submit, inline error). Emoji via `NcEmojiPicker`, color via `NcColorPicker`, income via `NcCheckboxRadioSwitch` (switch).

## Constraints

- Same as T1: Nextcloud 31–35, PHP 8.1, AppFramework conventions; Vue 3 Composition API + TypeScript + `@nextcloud/vue` + `@mdi/js`; no new runtime dependencies.

## Acceptance criteria

- [ ] `POST /api/categories` creates an owner-scoped category; rejects empty name, invalid color, and foreign/unknown parent; returns `201`.
- [ ] `POST /api/stores` creates an owner-scoped store; rejects empty name; returns `201`.
- [ ] Catalog navigation item opens the Catalog view with Categories/Stores tabs.
- [ ] Categories render as a nested tree with emoji/color/income visible; adding a category updates the tree.
- [ ] Stores render as a list; adding a store updates the list.
- [ ] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`, `npm run lint`, `npm run build` pass.
