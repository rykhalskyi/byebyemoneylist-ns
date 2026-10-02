---
created: 2026-10-02
type: spec
status: proposed
summary: Cross-user shopping-list sharing (Phase 1) — access layer, share tables, catalog visibility, purchaser attribution
---

# Spec — List sharing (Phase 1: single-list sharing)

Design for sharing a shopping list between two users. Source vision:
[`wiki/raw/09-list-sharing.md`](../../raw/09-list-sharing.md). Part of the
[web app epic](../epics/nextcloud-web-app.md).

## Summary

Sharing is not a data copy — the vision requires edits to propagate live between the
owner's and the guest's catalogs. The app is currently single-owner: every table
(`bbml_lists`, `bbml_list_items`, `bbml_products`, `bbml_categories`, `bbml_stores`)
has an `owner` column, and every mapper/controller filters reads and writes by it. The
design therefore adds an **access layer** on top of the existing tables. The only real
copy is the read-only "copy list to my DB" action.

## Requirements (Phase 1)

Three list states:

1. `NO_SHARING` — not shared.
2. `SHARED_READONLY` — guest sees the list and the owner's
   products/categories/stores and may copy them into their own DB, but cannot edit the
   originals and cannot use the owner's items directly.
3. `SHARED_READWRITE` — guest may use the list and its products/categories/stores as
   their own: purchase, add items, change category. The guest cannot edit or delete
   the owner's products/categories/stores. Adding one of the guest's own items to the
   shared list republishes it to the list owner (with confirmation).

Additional rules from the vision:

- A guest sees shared lists alongside their own, marked as another user's list.
- In the guest's catalog, own items appear in their own expander; each sharing user's
  shared items appear in separate expanders.
- In read/write, adding the guest's own item publishes it into the owner's catalog.
  Ownership stays with the guest; later edits/deletes propagate to the owner's view.
- Owner sees guest-added items.
- Recognition and purchase prefer own items; newly recognized items go to the guest's
  own catalog.
- Expenses/analytics attribute to the user who made the purchase, not the list owner.
- Revoking a share keeps the list as a name-only, greyed-out placeholder.

## Design

### Data model — two new tables

**`bbml_list_shares`** — authoritative list ACL:

- `id`, `list_id`, `owner` (uid of list owner, denormalized), `shared_with`, `mode`
  (readonly/readwrite), `status` (active/revoked), `created_at`, `updated_at`.
- Unique `(list_id, shared_with)`. Revoke sets `status = revoked` and keeps the row,
  which drives the greyed-out name placeholder.

**`bbml_catalog_shares`** — guest→owner publish direction only:

- `id`, `item_type` (product/category/store), `item_id`, `owner`, `shared_with`,
  `status`, `created_at`, `updated_at`.
- Unique `(item_type, item_id, shared_with)`.

The owner→guest direction needs no rows: "a shared list brings the owner's catalog
with it" is a rule, not data — the owner's catalog is visible iff an active share of
any list owned by that user exists for the guest. The guest→owner direction cannot be
derived, so it is materialized in `bbml_catalog_shares` when the guest adds an own
item to the owner's list.

### Access layer

New `lib/Service/Sharing/`:

- `ListAccessService`
  - `findReadable(id, uid)` / `findWritable(id, uid)` — replace `findByIdAndOwner()`
    at read vs write call sites.
  - `listIdsFor(uid)` and `visibleCatalogOwners(uid)` = `[self] + owners of lists
    actively shared with uid`.
  - `isOwner(listId, uid)` for owner-only operations (rename, delete, manage shares).
- `CatalogSharingService`
  - `publish(itemType, itemId, fromUid, toUid)` — called after the confirmation
    dialog.
  - `visibleOwnersForCatalog(uid)`.

### Mapper changes

- Add `ListMapper::findReadableByUser(uid)` = `owner = uid OR EXISTS active share`.
- Add `findAllVisibleByOwners(array ownerIds)` / `findByIdsVisible(...)` variants to
  `ProductMapper`, `CategoryMapper`, `StoreMapper`. Keep owner-only methods for write
  paths and ownership checks.
- Add `ListShareMapper`, `CatalogShareMapper`.
- Fix `ListItemController::index`: it resolves product names through
  `ProductMapper::findByIds(..., $userId)` and so silently drops the owner's products
  (blank names in readwrite). Use the visible owner set.

### Item semantics

`ListItemEntity.owner` already means "who added/purchased" — keep it as the expense
key. Mutation rule:

- Mutate an item iff `canWrite(list)` **and** (`item.owner === uid` **or**
  `list.owner === uid`).

Owner retains control of everything; a guest manages only items they added; guests
cannot delete each other's items in Phase 2.

### Expense attribution

`AnalyticsMapper::overview` and `DashboardMapper::applyBaseFilters` aggregate by
`l.owner` today and must move to item-level purchaser attribution:

- **Option A (recommended):** compute per-viewer totals from items in
  finished/non-subscription/non-income lists where `li.owner = viewer`. Each buyer
  sees only their own portion; `final_total` applies only to lists with no priced
  items.
- Option B: keep list-level `final_total` and add an attribution column — more
  schema, less accurate for split purchases.

### Revoke

Keep the share row; a revoked share returns the list name only
(`revoked: true`). The guest UI greys it and disables open.

### Touch points

- Read paths: `ListController::index`, `ListItemController::index`,
  `ProductController`/`CategoryController`/`StoreController` index → visible-owner
  scope.
- Write paths: `ListItemController::create/update/destroy` → access check + publish
  confirmation.
- `ReceiptCommitService` is owner-scoped throughout; recognition stays
  own-catalog-first and new items keep `owner = current user`. Verify no cross-owner
  matching.
- Frontend: `src/services/*`, `src/components/shoppinglists/*`,
  `src/components/catalog/*` — owner badge, per-user catalog expanders, copy action,
  confirm dialog.

## Phasing

1. Schema + `ListAccessService` + read-only list view + "copy to own DB".
2. `bbml_catalog_shares` + readwrite item management + catalog expanders + publish
   confirmation.
3. Analytics/dashboard purchaser attribution + revoke placeholders.
4. Phase 2 family groups (tables generalize: `shared_with` → group id).

## Open questions

- Publish confirmation persistence: the confirmed item lands in the owner's catalog
  permanently; deleting it later propagates — confirm no cascade rules needed when the
  owner archives/deletes the list.
- Whether the guest sees all of the owner's lists or only explicitly shared ones.
- Read-only copy: one-time snapshot vs linked; assumed snapshot.
