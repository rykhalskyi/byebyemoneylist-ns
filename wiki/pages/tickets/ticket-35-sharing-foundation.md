---
created: 2026-10-02
type: ticket
status: in-progress
summary: T35 — Sharing foundation (share tables, access layer, share CRUD API)
---

# T35 — Sharing foundation

Part of [epic](../epics/nextcloud-web-app.md) (T12). Design: [specs/list-sharing](../specs/list-sharing.md).

## Spec

Backend-only foundation for sharing. No guest UI yet.

- Migration `Version1011Date20261002` creating:
  - `bbml_list_shares` (`id, list_id, owner, shared_with, mode, status, created_at,
    updated_at`, unique `(list_id, shared_with)`)
  - `bbml_catalog_shares` (`id, item_type, item_id, owner, shared_with, status,
    created_at, updated_at`, unique `(item_type, item_id, shared_with)`)
- Entities + `ListShareMapper`, `CatalogShareMapper`.
- `ListAccessService`: `findReadable`/`findWritable`/`listIdsFor`/
  `visibleCatalogOwners`/`isOwner`.
- `CatalogSharingService`: `publish()`/`visibleOwnersForCatalog()`.
- Visible-owner query variants on `ListMapper`, `ProductMapper`, `CategoryMapper`,
  `StoreMapper` (keep owner-only methods for write/ownership checks).
- `ShareController` OCS endpoints: create share, revoke share, list incoming/outgoing.
- Revoke keeps the row (`status=revoked`); readable lookup returns name-only
  placeholder (`revoked: true`).
- Unit tests for services, mappers and controller.

## Plan

1. `lib/Migration/Version1011Date20261002.php` — both tables + indexes.
2. `lib/Entity/ListShareEntity.php`, `lib/Entity/CatalogShareEntity.php`.
3. `lib/Db/ListShareMapper.php`, `lib/Db/CatalogShareMapper.php`.
4. `lib/Service/Sharing/ListAccessService.php`, `lib/Service/Sharing/CatalogSharingService.php`.
5. `lib/Controller/ShareController.php` (+ `#[ApiRoute]` attributes); no DI wiring needed.
6. `lib/Db/{List,Product,Category,Store}Mapper.php` — visible variants.
7. `tests/unit/...` — service + controller + mapper coverage.

## Outcome

Backend foundation implemented (2026-10-02).

- `lib/Migration/Version1011Date20261002.php` — created `bbml_list_shares` and
  `bbml_catalog_shares` (unique `(list_id, shared_with)` / `(item_type, item_id,
  shared_with)`, all index names ≤ 30 chars). Applied on the dev instance
  (`occ migrations:migrate` → current `1011Date20261002`); verified in MySQL.
- `lib/Entity/ListShareEntity.php`, `lib/Entity/CatalogShareEntity.php` — string
  `mode`/`status` constants; `readonly`/`readwrite`, `active`/`revoked`,
  `product`/`category`/`store`.
- `lib/Db/ListShareMapper.php`, `lib/Db/CatalogShareMapper.php`; `ListMapper::findById()`.
- `lib/Service/Sharing/ListAccessService.php` (`findReadable`/`findWritable`/`isOwner`/
  `listIdsFor`/`visibleCatalogOwners`) and `CatalogSharingService.php` (`publish`
  idempotent/reactivates revoked, `revokeItem`, `visibleOwnersForCatalog`).
- `lib/Controller/ShareController.php` — `GET /api/shares/incoming`,
  `GET|POST /api/lists/{id}/shares`, `DELETE /api/lists/{id}/shares/{shareId}`;
  revoke is soft (`status=revoked`), `incoming` returns name-only for revoked.
- Tests: `tests/unit/Service/Sharing/ListAccessServiceTest.php`,
  `tests/unit/Service/Sharing/CatalogSharingServiceTest.php`,
  `tests/unit/Controller/ShareControllerTest.php` (24 tests).
- Verified: `composer run test:unit` (229 tests), `composer run psalm`,
  `composer run cs:check`, `composer run openapi` (4 new routes in `openapi.json`).
- Deferred to [T36](ticket-36-sharing-guest-readonly.md): the visible-owner query
  variants on the product/category/store mappers (no consumer yet, would be dead code).
- Not committed.
