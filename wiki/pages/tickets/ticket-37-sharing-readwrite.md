---
created: 2026-10-02
type: ticket
status: implemented
summary: T37 — Read/write sharing (guest manages own items, uses owner catalog, publish confirmation)
---

# T37 — Read/write sharing

Part of [epic](../epics/nextcloud-web-app.md) (T12). Design: [specs/list-sharing](../specs/list-sharing.md).

## Spec

`SHARED_READWRITE` guest experience, building on [T35](ticket-35-sharing-foundation.md)
and [T36](ticket-36-sharing-guest-readonly.md).

- Guest can purchase in the shared list, add items, and change category.
- Guest can reference the owner's catalog items; cannot edit/delete the owner's
  products/categories/stores.
- Item mutation rule: `canWrite(list)` and (`item.owner === uid` or
  `list.owner === uid`).
- Adding one of the guest's own items to the shared list asks for confirmation and,
  on confirm, calls `CatalogSharingService::publish()` so it appears in the owner's
  catalog under a shared expander. Owner sees guest-added items.
- Later edits/deletes by the item owner propagate to the other user's view.
- Fix product-name resolution in `ListItemController::index` to use visible owners.

## Plan

1. `lib/Controller/ListItemController.php` — access via `ListAccessService`, ownership
   mutation rule, visible-owner product lookup, publish trigger.
2. `lib/Controller/ProductController.php` etc. — allow referencing visible items,
   block owner-item mutation.
3. `lib/Receipt/...` — confirm recognition stays own-catalog-first; new items keep
   `owner = current user`.
4. Frontend: owner-item read-only affordances, publish confirmation dialog, catalog
   expanders become usable in read/write.
5. Tests: mutation authorization matrix, publish propagation.

## Outcome

T37a — backend read/write implemented (2026-10-02).

- `ListItemController` create/update/destroy authorize via
  `ListAccessService::findWritable` (owner or active read/write share). Item
  mutation requires `item.owner === user` or `list.owner === user`.
- `create` may reference a product visible through the owner set (not only the
  caller's own); `publishToOwner=true` publishes the caller's own product to the
  list owner via `CatalogSharingService` (the confirmation dialog is the client's
  job).
- `ListAccessService::visibleCatalogOwners` covers whole catalogs shared via list
  shares; the guest→owner direction exposes only the specific items published via
  `bbml_catalog_shares` (see [T41](ticket-41-sharing-scope-hardening.md), D-25).
- Removed the now-unused `ProductMapper::findByIds`.
- Tests: shared-list create/update/destroy authorization, owner-product use,
  publish flag.
- Verified: 244 tests, psalm, cs, openapi.
- T37b — frontend publish confirmation (2026-10-02): `AddProductDialog` now
  receives the target list's `sharedOwner`; adding the user's own product to a
  shared list shows a confirmation dialog and submits with `publishToOwner: true`.
  Owner products (already shared) and own lists submit directly. Verified: 214
  frontend tests, lint, stylelint, build.
- T37 complete for the current publish path (list items are products). Revoking a
  published product's grant on delete is wired in
  [T41](ticket-41-sharing-scope-hardening.md).
