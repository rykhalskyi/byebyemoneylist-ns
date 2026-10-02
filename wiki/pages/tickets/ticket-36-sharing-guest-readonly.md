---
created: 2026-10-02
type: ticket
status: proposed
summary: T36 — Read-only sharing (guest sees marked lists, catalog expanders, copy to own DB)
---

# T36 — Read-only sharing (guest view)

Part of [epic](../epics/nextcloud-web-app.md) (T12). Design: [specs/list-sharing](../specs/list-sharing.md).

## Spec

`SHARED_READONLY` guest experience, building on [T35](ticket-35-sharing-foundation.md).

- Guest sees shared lists alongside own, clearly marked as another user's list.
- Read-only list detail: no item mutation; owner's items shown, not editable.
- Guest catalog shows the owner's products/categories/stores in separate
  read-only expander(s) (owner→guest visibility rule).
- "Copy to my DB" action: one-time snapshot duplicating the list and its
  products/categories/stores into the guest's own catalog.
- Revoked shares render as a greyed-out, name-only placeholder that cannot be opened.

## Plan

1. `lib/Controller/ListController.php` / `ListItemController.php` — read paths use
   `ListAccessService::findReadable`/`listIdsFor`; serialize `sharedBy`/`revoked`.
2. `lib/Controller/ProductController.php` / `CategoryController.php` /
   `StoreController.php` — read paths include visible catalog owners.
3. New copy endpoint (e.g. `POST /api/lists/{id}/copy`) — transactional snapshot.
4. `src/services/*` — types + API for shared/revoked markers and copy.
5. `src/components/shoppinglists/*` — shared badge, greyed revoked row.
6. `src/components/catalog/*` — read-only "shared by X" expanders.
7. Tests: copy transaction, read scoping, component snapshots.

## Outcome

Not started.
