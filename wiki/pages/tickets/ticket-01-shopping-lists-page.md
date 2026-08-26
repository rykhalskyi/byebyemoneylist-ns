---
created: 2026-08-26
type: ticket
tags: [ticket, ui, shopping-lists, backend]
related:
  - "../epics/nextcloud-web-app.md"
  - "../project-overview.md"
  - "../specs/shopping-lists-page.md"
---

# T1 — Shopping Lists page (view lists + add a new list)

Part of [epics/nextcloud-web-app](../epics/nextcloud-web-app.md).

## Summary

First deliverable of the web UI: a **Shopping Lists** screen showing the current user's shopping lists, with the ability to **create a new list**. Establishes the first backend entity/API and the first real Vue view wired to it.

Source: [`~/Documents/Draft/bbml-nc-ideas.md` §2 Shopping Lists](file:///home/admin/Documents/Draft/bbml-nc-ideas.md).

## Description

The Shopping Lists page lists the user's shopping lists (name, store, status, total, dates) and offers an "Add list" action. Creating a list opens a dialog to enter a name (required) and optionally pick a store and a category; submitting persists the list server-side and it appears in the list immediately.

## Requirements

- Show all lists belonging to the current user, newest first.
- Each list row/card displays: name, status badge (new / finished / archived), store, category, `finalTotal` (formatted with the user currency, default none), creation date.
- "Add list" button (visible always when there are no lists too — empty state with a call to action).
- Add-list dialog: name input (required), optional store selector, optional category selector. Creates an **empty** list with status `new`.
- Lists are persisted server-side per user via the app's own API (not the old folder sync).
- Empty list is allowed (a list may contain zero items).
- After creation, the new list appears at the top of the list.

## Scope

In scope:
- Backend: `bbml_lists`, `bbml_stores`, `bbml_categories` tables; `List`/`Store`/`Category` entities + mappers; `ListController` with `index` (GET all for current user) and `create` (POST) OCS endpoints; read-only `StoreController::index` / `CategoryController::index` for the dialog selectors; migration.
- Frontend: `ShoppingLists.vue` view wired into `App.vue` "Shopping Lists" navigation item; list rendering + add dialog; `@nextcloud/axios` API calls.
- Unit tests for the controllers (mocked mappers).

Out of scope (later tickets):
- List detail / items (T2), finishing a purchase (T3), sharing (T12), sync API (T13), editing/deleting lists, duplicate list.

## UI/UX considerations

- Use Nextcloud design system (`@nextcloud/vue`): `NcAppContent`, cards, `NcButton` for "Add list", `NcModal`/`NcDialog` for creation, `NcTextField`, `NcSelect` for store/category.
- Empty state with illustration + primary "Add list" button.
- Respect the current user's currency setting when formatting `finalTotal` (default: no symbol yet, since currency setting is T11).

## API design

```
GET  /apps/byebyemoneylist/api/lists       → { lists: [{ id, name, storeId, categoryId, finalTotal, status, createdAt }] }
POST /apps/byebyemoneylist/api/lists       → { list: {...} }   (body: { name, storeId?, categoryId? })
GET  /apps/byebyemoneylist/api/stores      → { stores: [...] }
GET  /apps/byebyemoneylist/api/categories  → { categories: [...] }
```

Responses filtered to the current user; `createdAt` returned so the frontend can sort.

## Design decisions

- **Server-side persistence now** — introduce the real data layer from the start (entity + mapper + DB migration) instead of mocking, so T2+ build on it.
- **Empty list as the only creation mode** for T1 — items, prices and stores/categories pickers get richer in later tickets; keep the dialog minimal here.
- Store/category are optional in T1 and stored as nullable FKs; tables/entities created now, full store/category CRUD lands in T4/T5.
- Separate `finalTotal` (actual price) from item totals — the dual-price model is honored by the schema from day one.
- **List `id` is a random UUID** (generated server-side at creation), not an auto-increment integer — opaque, safe to share, and merge-friendly across devices.
- **Owner from day one** — every list has an owner (the creating user); only the owner may delete or change sharing (enforced server-side in T12, but the `owner` column exists from the start).
- **One store, several categories per list** — per source §2.5 a normal list has exactly one store and can carry one or several categories; T1 keeps the dialog minimal (single optional store + category) but the schema stores store as a single `store_id` FK and categories as a single `category_id` FK now, ready for a multi cross-ref.
- **Empty list with a price** — source §2.4: an empty list may still have a `finalTotal` (a purchase logged without items); the schema must allow zero-item finished lists with a price, though the quick-purchase flow itself lands in T3.
- **Store/category tables from day one** — `bbml_stores`/`bbml_categories` tables + entities are created in T1 (lists reference them by FK) and served via read-only endpoints so the dialog selectors are data-driven; full CRUD lands in T4/T5.

## Acceptance criteria

- [ ] Migration creates `bbml_lists`, `bbml_stores`, `bbml_categories` cleanly (`occ migrations:execute` or via AppFramework).
- [ ] `GET /api/lists` returns only the current user's lists.
- [ ] `POST /api/lists` creates an empty `new` list and returns it; validation rejects missing name.
- [ ] `GET /api/stores` and `GET /api/categories` return only the current user's rows.
- [ ] Shopping Lists navigation item opens the new view.
- [ ] Lists render with name, status, store, category, total, date.
- [ ] "Add list" works from both populated and empty states; new list appears at top.
- [ ] `npm run lint`, `composer lint`, `composer test:unit`, `composer cs:check` pass.

## Files (planned)

- `lib/Entity/{List,Store,Category}Entity.php`, `lib/Db/{List,Store,Category}Mapper.php`, `lib/Migration/Version1000Date20260826.php`, `lib/Util/Uuid.php`
- `lib/Controller/{List,Store,Category}Controller.php`
- `src/views/ShoppingLists.vue` (new), `src/components/NewListDialog.vue` (new), wiring in `src/App.vue`, `src/types.ts`, `src/services/listsApi.ts`
- `tests/unit/Controller/{List,Store,Category}ControllerTest.php`

## Status

**Implemented (2026-08-26).** All files above created; migration `Version1000Date20260826` applied on the dev instance (`nextcloud.local`); `occ migrations:status` reports `1000Date20260826`. Verified end-to-end: `GET/POST /api/lists`, `GET /api/stores`, `GET /api/categories` (JSON, owner-scoped), 422 on empty name. `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit` (10 tests), `npm run lint`, `npm run build`, `composer openapi` all pass.

## Updates

- [2026-08-26]: Design decision — list `id` is a random server-generated UUID, not an auto-increment integer.
- [2026-08-26]: Enriched from source §2 — added source link, owner-from-day-one, one-store/multi-category schema note, empty-list-with-price.
- [2026-08-26]: Data model revised — store/category become nullable FKs (`store_id`/`category_id`); `bbml_stores`/`bbml_categories` tables + entities added in T1 with read-only endpoints; placeholder `ApiController` removed.
- [2026-08-26]: Implemented. Notes: OCS `ApiRoute` URLs are `/api/lists` etc.; `doctrine/dbal` added to `require-dev` (needed for migration type constants + psalm); scaffold `composer.json` bugs fixed (invalid multi-line `description`, empty `homepage`); psalm-level-1 suppressions added for OCP `Entity` id/getter idiosyncrasies and multi-status `DataResponse` generics.
