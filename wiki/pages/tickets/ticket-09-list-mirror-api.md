---
created: 2026-09-05
type: ticket
tags: [ticket, backend, shopping-lists, sync, mirror, list-categories, updated_at, is_finished]
related:
  - "../epics/nextcloud-web-app.md"
---

# T9 — List mirror API (shopping-list sync, server side)

Server half of **Phase 3 — Shopping List Sync** for the Android ↔ Nextcloud
mirror (defined in the Android repo wiki as
`plans/08-shopping-list-sync.md`, tickets 1–3). This is that plan's **Ticket 1**:
give lists/items the full mirror surface (create/update/delete + client field
set) so a linked list can be pushed/pulled losslessly.

## Summary

Lists and items now serialize and accept the client's full field set, lists can
be updated (`PUT`) and deleted (`DELETE`), a list–category junction stores the
client's *many categories per list*, and both tables carry an `updated_at`
timestamp for change detection plus `is_finished` as the authoritative finish
flag. Existing web-app behaviour is preserved (`categoryId`/`status`/`createdAt`
still returned).

## API design

```
GET    /api/lists                                    (adds categoryIds, position, purchaseDate, isFinished, updatedAt, createDate)
POST   /api/lists                                    (adds categoryIds[], position, purchaseDate, isFinished, finalTotal, createDate)
PUT    /api/lists/{id}                               (NEW — full-state push; null scalars clear)
DELETE /api/lists/{id}                               (NEW — cascades items + list-category rows)
GET    /api/lists/{id}/items                         (adds position, discount, customName, updatedAt)
POST   /api/lists/{id}/items                         (adds position, discount, customName)
PUT    /api/lists/{id}/items/{itemId}                (adds position, discount, customName)
DELETE /api/lists/{id}/items/{itemId}                (unchanged — used by client item full-replace)
```

## Decisions

- **Migration `Version1006Date20260905`** (plan's manifest said *1005*, but
  `Version1005Date20260831` already exists — bumped to the next free number).
  Adds to `bbml_lists`: `position`, `purchase_date`, `is_finished`,
  `updated_at`; to `bbml_list_items`: `position`, `discount`, `custom_name`,
  `updated_at`; creates junction `bbml_list_categories` (uuid `id`, `list_id`,
  `category_id`, indexed on both). `postSchemaChange` backfills `updated_at =
  created_at`, `is_finished = (status='finished')`, and copies the legacy
  single `category_id` into the junction.
- **`status` kept for the web app; `is_finished` is the mirror flag.** When the
  server receives `isFinished=true` it also sets `status='finished'`; receiving
  `isFinished=false` moves a `finished` list back to `new` so the web lifecycle
  stays consistent. (Resolves plan Open Question #1.)
- **Client `createDate` is preserved** on push: `POST /api/lists` accepts an
  optional `createDate` and uses it as `created_at` instead of "now".
  (Resolves plan Open Question #2.)
- **Category dual-write on create/update**: the junction is authoritative and is
  also written to the legacy `category_id` column (first category) so direct DB
  consumers and old single-category flows keep working.
- **`updated_at` stamped on every create/update** of a list or item.
- Item updates stay *partial* (null = skip, matching the existing item PUT);
  list `PUT` is a *full-state* push (name required, null scalars clear, booleans
  only change when sent).

## Files

- `lib/Migration/Version1006Date20260905.php` — create (schema + backfill)
- `lib/Entity/ListEntity.php`, `lib/Entity/ListItemEntity.php` — new fields
- `lib/Db/ListMapper.php` — junction read/replace/delete helpers
- `lib/Db/ListItemMapper.php` — `deleteByListId` (list delete / full-replace)
- `lib/Controller/ListController.php` — `PUT`/`DELETE`/`POST` fields, junction
  writes, `serializeList` shape
- `lib/Controller/ListItemController.php` — `position`/`discount`/`customName`,
  `updatedAt` on create/update/serialize
- `lib/Controller/CategoryController.php` — category delete also removes its
  `bbml_list_categories` rows
- `tests/unit/Controller/ListControllerTest.php` (+ new update/delete/dates/
  multi-category tests), `ListItemControllerTest.php` (+ field tests),
  `CategoryControllerTest.php` (query-builder mock now covers `delete()`)

## Verification

- Migration applied on the dev instance (`occ migrations:migrate` → 1006);
  new columns and junction confirmed via SQL; backfill ran cleanly (dev DB had
  no legacy rows).
- End-to-end over the OCS API (alice on nextcloud.local): list create with
  store/categories/dates/createDate, list update (finish + clear store &
  categories), item create/update with the new fields, list delete cascade —
  all round-trip correctly; `isFinished` toggling moves `status`
  `new ⇄ finished`.
- `phpunit`: all list/item/category tests pass. The 3 `ProductControllerTest`
  failures and the psalm errors in `CategoryController::batchCreate` are
  **pre-existing on `main`** (confirmed by running against the unmodified tree)
  and are unrelated to this ticket.
- `php-cs-fixer` clean on all touched files; psalm introduces **no new** errors.

## Updates

- [2026-09-05]: Post-review fixes (PR #18). `PUT /api/lists/{id}` now clears
  `finalTotal` when a null is pushed (previously the null-clearing scalar kept
  its stored value, contradicting the full-state contract). `parseDate()` is now
  strict ISO-8601 (requires `T`, `Z`/`±hh:mm` offset, optional fraction),
  rejects relative/date-only input with 422, treats blank values as absent in
  `create()` (previously blank `createDate`/`purchaseDate` 422'd while `update`
  treated them as absent), and normalizes parsed values to UTC before persist
  (avoids an offset-shift when DATETIME is round-tripped). Removed the unused
  `ListMapper::deleteCategoriesByCategoryId()`; `CategoryController::destroy`
  keeps its inline delete. Added unit tests: finalTotal null-clear, blank-date
  acceptance, offset→UTC normalization, and date-without-time rejection.
