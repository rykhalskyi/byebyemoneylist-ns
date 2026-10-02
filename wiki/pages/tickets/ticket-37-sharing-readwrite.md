---
created: 2026-10-02
type: ticket
status: proposed
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

Not started.
