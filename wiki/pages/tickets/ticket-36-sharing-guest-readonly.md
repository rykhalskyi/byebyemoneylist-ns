---
created: 2026-10-02
type: ticket
status: in-progress
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

T36a — backend list read scoping implemented (2026-10-02).

- `ListController::index` now returns owned lists **plus** lists shared with the
  user, appended with `sharedBy`, `shareMode` and `revoked` (revoked rows are the
  name-only greyed placeholder). Read path only; rename/delete stay owner-only.
- `ListItemController::index` authorizes via `ListAccessService::findReadable` and
  resolves product names through `ProductMapper::findByIdsForOwners` over the
  visible owner set, so items on a shared list show the owner's product names.
  Writes still require ownership (read/write lands in [T37](ticket-37-sharing-readwrite.md)).
- `ProductMapper` gained the owner-set lookup; the earlier catalog-wide visible
  variants (`Category`/`Store`/`Product` index) were deliberately reverted and
  deferred to the next step to avoid dead code.
- Tests: shared/revoked markers in `ListControllerTest`, shared-list read in
  `ListItemControllerTest`.
- Verified: `test:unit` (231 tests), `psalm`, `cs:check`, `openapi` (list response
  gains `sharedBy`/`shareMode`/`revoked`).
- Still open: copy-to-own-DB endpoint, and all frontend.
- T36b — catalog visibility (2026-10-02): `Category`/`Store`/`Product` `index`
  endpoints now read across `visibleCatalogOwners` (`findAllByOwners` /
  `findAllVisibleByOwners`) and each serialized item carries `owner` + `shared`, so
  a guest sees the owner's catalog in separate expanders. Aliases and last prices
  resolve across the same owner set (`findByProductIdsForOwners` /
  `findLatestByProductIdsForOwners`). Removed the now-redundant owner-only
  `ProductMapper::{findAllByOwner,findSubscriptionsByOwner,findIncomeByOwner}`.
  Verified: 234 tests, psalm, cs, openapi.
