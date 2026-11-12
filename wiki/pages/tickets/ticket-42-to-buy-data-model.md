---
created: 2026-10-13
type: ticket
status: in-progress
summary: T42 — To Buy data model: kind + is_active columns and nullable product_id (migration 1013)
---

# T42 — To Buy data model & migration

First ticket of the [To Buy list redesign](../specs/to-buy-list-redesign.md)
([plan](../plans/to-buy-list-redesign.md)).

## Spec

Give lists an explicit `kind` (`NEED_TO_BUY | PURCHASE | INCOME | SUBSCRIPTION`) and an
`is_active` flag, and let list items be product-free so a To Buy item can be plain text
(D-28, D-29). Backfill existing rows from the legacy flags and convert open-list items to
plain text (D-33).

## Plan

1. `lib/Entity/ListEntity.php` — add `kind ?string` and `isActive ?bool` (+ `addType`).
2. `lib/Entity/ListItemEntity.php` — `setProductId(?string)` / nullable `productId`.
3. `lib/Migration/Version1013Date20261112.php`:
   - `bbml_lists`: add `kind` (string 32, nullable), `is_active` (bool, default false).
   - `bbml_list_items`: make `product_id` nullable.
   - `postSchemaChange` (inject `IDBConnection`): normalize `status` `archived` →
     `finished` (D-37), backfill `kind` (income → INCOME, subscription → SUBSCRIPTION,
     status finished → PURCHASE, else NEED_TO_BUY), activate the newest To Buy list per
     owner only if none is active yet, then convert To Buy items to plain text (D-33):
     `custom_name = COALESCE(custom_name, product.name)`, `product_id = NULL`,
     `quantity = 1.0`, `price = NULL`, `discount = NULL`.
4. `appinfo/info.xml` version bump.
5. `tests/unit/**` for a serialization/derivation case.

## Outcome

In progress on `feature/to-buy-list-redesign`: `kind`/`isActive` (+ `resolveKind()`) on
`ListEntity`, nullable `ListItemEntity::setProductId()`, migration
`Version1013Date20261112` (archive normalization, kind backfill, newest-active selection,
plain-text item conversion) and app version `1.3.0`. Unit tests green (284); migration
dry-run on the dev instance still pending.
