---
created: 2026-10-03
type: ticket
status: implemented
summary: T41 — List-sharing review fixes: item-scoped catalog grants, shared-list payload, revoked placeholder, share/grant lifecycle
---

# T41 — List sharing scope hardening

Follow-up review of [T35](ticket-35-sharing-foundation.md)–[T40](ticket-40-sharing-owner-mark.md)
against `main`. Design: [specs/list-sharing](../specs/list-sharing.md).

## Spec

Fix findings from a branch review, in priority order:

1. **Catalog scope leak.** `visibleCatalogOwners` promoted a single published
   product to whole-catalog visibility, so the list owner could read the guest's
   entire catalog. Per the spec, the guest→owner direction must be **item-scoped**.
2. Shared lists in `GET /api/lists` lost their `categoryIds` and `totalPrice`
   because totals/categories were computed only for owned list ids.
3. `ProductInfoDialog` called owner-scoped price/picture endpoints for shared
   products (failed requests, dead upload/delete buttons).
4. `CatalogSharingService::revokeItem` was never wired (open T37 item).
5. Deleting a list left orphan `bbml_list_shares` rows.
6. Revoked lists still offered "Copy to my catalog".
7. Revoked shares serialized full list metadata instead of a name-only placeholder.

## Plan

1. Split visibility: `visibleCatalogOwners` = self + active **list-share** owners;
   add `ListAccessService::grantedCatalogItemIds(uid, type)` and
   `CatalogShareMapper::findActiveItemIdsByRecipient(uid, type)`. `ProductMapper`
   query becomes `owner IN (...) OR id IN (granted)`.
2. `ListController::index` computes totals/categories over owned **and active shared**
   list ids.
3. Skip price/picture fetch and hide those controls when `product.shared`.
4. Revoke product grants in `ProductController::destroy` (same transaction).
5. `ListShareMapper::deleteByListId()` called from `ListController::destroy`.
6. `ShoppingListRow` hides Copy when `revoked`.
7. `ListController::serializeRevokedList()` name-only payload.

## Outcome

Implemented (2026-10-03).

- Guest→owner catalog visibility is item-scoped: the owner sees only products the
  guest explicitly published (`bbml_catalog_shares`), each under the guest's
  "Shared by {guest}" expander; the rest of the guest's catalog stays private.
  Product names on shared lists resolve owner-agnostically via `ProductMapper::findByIds`
  (ids come from readable lists). See D-25.
- `ListController::index` returns `categoryIds`/`totalPrice` for active shared lists;
  revoked shares return a name-only placeholder (D-26).
- `ProductController::destroy` calls `CatalogSharingService::revokeItem` inside the
  delete transaction; `ListController::destroy` deletes the list's share rows.
- `ProductInfoDialog` is sharing-aware (no owner-scoped calls for shared products);
  `ShoppingListRow` hides Copy for revoked lists.
- Files: `lib/Service/Sharing/ListAccessService.php`, `lib/Db/{CatalogShare,ListShare,Product}Mapper.php`,
  `lib/Controller/{List,ListItem,Product}Controller.php`,
  `src/components/ProductInfoDialog.vue`, `src/components/shoppinglists/ShoppingListRow.vue` (+ tests).
- Verified: `composer run test:unit` (248), psalm, cs:check, openapi (no change);
  `npm run test` (226), lint, stylelint, build.
