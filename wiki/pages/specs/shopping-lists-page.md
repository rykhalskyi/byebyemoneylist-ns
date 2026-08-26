---
created: 2026-08-26
type: spec
tags: [spec, ui, shopping-lists, backend]
related:
  - "../plans/shopping-lists-page.md"
  - "../tickets/ticket-01-shopping-lists-page.md"
---

# Spec — Shopping Lists page (view lists + add a new list)

First deliverable of the web UI (see [epic](../epics/nextcloud-web-app.md), [ticket](../tickets/ticket-01-shopping-lists-page.md)).

Source: [`~/Documents/Draft/bbml-nc-ideas.md` §2 Shopping Lists](file:///home/admin/Documents/Draft/bbml-nc-ideas.md).

## Summary

A **Shopping Lists** screen showing the current user's shopping lists with the ability to **create a new list**. This establishes the first backend entity/API and the first real Vue view wired to it.

## Requirements

- Show all lists belonging to the current user, newest first.
- Each list row displays: name, status badge (new / finished / archived), store, category, `finalTotal` (formatted with the user currency, default none), creation date.
- "Add list" action always visible — including the empty state, which is a call-to-action.
- Add-list dialog: name input (required), optional store selector, optional category selector. Creates an **empty** list with status `new`.
- Lists are persisted server-side per user via the app's own API (not the old folder sync).
- Empty list is allowed (a list may contain zero items).
- After creation, the new list appears at the top of the list.

## Scope

In scope:
- Backend: `bbml_lists`, `bbml_stores`, `bbml_categories` tables; `List`/`Store`/`Category` entities + mappers; `ListController` with `index` (GET all for current user) and `create` (POST) OCS endpoints; read-only `StoreController::index` and `CategoryController::index` to populate the dialog selectors (full CRUD lands in T4/T5); migration.
- Frontend: `ShoppingLists.vue` view wired into `App.vue` "Shopping Lists" navigation item; list rendering + add dialog; `@nextcloud/axios` API calls.
- Unit tests for the controllers (mocked mappers).

Out of scope (later tickets):
- List detail / items (T2), finishing a purchase (T3), store/category CRUD (T4/T5), sharing (T12), sync API (T13), editing/deleting lists, duplicate list.

## Data model

All tables use a random UUID primary key (server-generated) plus an `owner` column (the creating user).

`bbml_lists` table:

| Column | Type | Notes |
|--------|------|-------|
| `id` | string (UUID) | Random, generated server-side at creation |
| `owner` | string (user id) | Creating user; owner-only ops enforced later (T12) |
| `name` | string | Required, trimmed |
| `store_id` | string (UUID)/null | FK to `bbml_stores.id`; single store per list (§2.5) |
| `category_id` | string (UUID)/null | FK to `bbml_categories.id`; single category in T1 (multi via cross-ref later, §2.5) |
| `status` | string | `new` / `finished` / `archived`; created as `new` |
| `final_total` | decimal/null | Actual paid price, separate from item totals (dual-price model) |
| `created_at` | datetime | Server time, used for newest-first sort |

`bbml_stores`: `id`, `owner`, `name` (address/logo/receipt-name columns deferred to T5).
`bbml_categories`: `id`, `owner`, `name`, `color` (nullable), `emoji` (nullable), `parent_id` (nullable self-FK, hierarchy-ready), `income` (bool, default false).

Multi-category cross-ref and the empty-list-with-price case are schema-ready but wired in later tickets.

## API design

```
GET  /apps/byebyemoneylist/api/lists       → { lists: [{ id, name, storeId, categoryId, status, finalTotal, createdAt }] }
POST /apps/byebyemoneylist/api/lists       → { list: {...} }   (body: { name, storeId?, categoryId? })
GET  /apps/byebyemoneylist/api/stores      → { stores: [{ id, name }] }
GET  /apps/byebyemoneylist/api/categories  → { categories: [{ id, name, color, emoji, parentId, income }] }
```

Responses filtered to the current user. Lists return `storeId`/`categoryId`; the frontend resolves store/category names from the `/stores` and `/categories` responses it already fetches for the dialog. `createdAt` returned so the frontend can sort.

## Design decisions

- **Server-side persistence now** — real data layer (entity + mapper + migration) from the start, so T2+ build on it.
- **Empty list as the only creation mode** for T1; keep the dialog minimal.
- **List `id` is a random UUID** (server-generated) — opaque, safe to share, merge-friendly.
- **Owner from day one** — `owner` column from the start; delete/share permission enforced in T12.
- **One store, several categories per list** — store single `store_id` FK, categories `category_id` FK now, multi cross-ref later.
- **Empty list with a price** — schema allows zero-item finished lists with `finalTotal`; quick-purchase flow in T3.
- Store/category are optional in T1 and nullable FKs; tables + entities are created now (read-only endpoints), full CRUD in T4/T5.

## UI/UX considerations

Layout and behavior follow the Nextcloud design system (`@nextcloud/vue`).

### Page layout

- Wrap the view in `NcContent` → `NcAppContent`. Keep the existing `Menu.vue` app-navigation for the five top-level views; the lists themselves are **content**, not navigation items (mail-style list-in-nav is overkill until list detail exists in T2).
- Render lists as `NcListItem`s in a scrollable list: leading icon/avatar, first line = name, second line = `store · date`, trailing = `finalTotal`. `NcListItem` provides hover and action slots for free.
- Header row above the list with the primary **"Add list"** `NcButton`.
- Status rendered as a small colored `NcChip` (or styled span) per status (`new` / `finished` / `archived`).

### Empty / loading / error states

- Empty: `NcEmptyContent` with an icon + short description + the primary "Add list" button in the action slot.
- Loading: centered `NcLoadingIcon` while the initial fetch is in flight.
- Error: `NcEmptyContent` variant with a retry action — never a silent dead screen.

### New-list dialog

- Use `NcDialog` (modern replacement for `NcModal`): title **"New list"**, footer with primary "Create" and secondary "Cancel". Enter submits, Esc cancels.
- Fields:
  - `NcTextField` for **name** (required; trimmed; helper/error text when empty).
  - `NcSelect` for **store** (optional, clearable).
  - `NcSelect` for **category** (optional, clearable).
- Validation & flow:
  - "Create" disabled until name is non-empty; error text on the field when submitting empty.
  - Block double-submit: loading state on "Create" while the POST is in flight.
  - Autofocus the name field on open.
  - On success: close dialog, prepend the new list to the top of the list.
  - On failure: keep dialog open and show an inline error.
- Use `@nextcloud/axios` for API calls (CSRF handled automatically).

### Formatting

- `finalTotal` via `Intl.NumberFormat` with the user's currency setting (T11); no symbol when unset.
- Dates localized via the user locale.
- Newest-first ordering.

## Constraints

- Must run on Nextcloud 31–35, PHP 8.1, AppFramework conventions (entities, mappers, migrations).
- Vue 3 Composition API (`<script setup>`), TypeScript, `@nextcloud/vue` components, `@mdi/js` icons.
- Icons/theme tokens must follow Nextcloud theming (dark mode, high contrast).
- Runtime dependencies: `@nextcloud/axios`, `@nextcloud/router` (no others added).

## Acceptance criteria

- [ ] Migration creates `bbml_lists`, `bbml_stores`, `bbml_categories` cleanly (`occ migrations:execute` or via AppFramework).
- [ ] `GET /api/lists` returns only the current user's lists.
- [ ] `POST /api/lists` creates an empty `new` list and returns it; validation rejects missing name.
- [ ] `GET /api/stores` and `GET /api/categories` return only the current user's rows.
- [ ] Shopping Lists navigation item opens the new view.
- [ ] Lists render with name, status, store, category, total, date.
- [ ] "Add list" works from both populated and empty states; new list appears at top.
- [ ] `npm run lint`, `composer lint`, `composer test:unit`, `composer cs:check` pass.

## Updates

- [2026-08-26]: Data model revised — store/category become nullable FKs (`store_id`/`category_id`) with `bbml_stores`/`bbml_categories` tables + entities created in T1; read-only store/category endpoints added for the dialog selectors.
