---
created: 2026-10-13
type: ticket
status: proposed
summary: T42 — To Buy data model: kind + is_active columns and nullable product_id (migration 1012)
---

# T42 — To Buy data model & migration

First ticket of the [To Buy list redesign](../specs/to-buy-list-redesign.md)
([plan](../plans/to-buy-list-redesign.md)).

## Spec

Give lists an explicit `kind` (`NEED_TO_BUY | PURCHASE | INCOME | SUBSCRIPTION`) and an
`is_active` flag, and let list items be product-free so a To Buy item can be plain text
(D-28, D-29). Backfill existing rows from the legacy flags without rewriting item data
(D-33).

## Plan

1. `lib/Entity/ListEntity.php` — add `kind ?string` and `isActive ?bool` (+ `addType`).
2. `lib/Entity/ListItemEntity.php` — `setProductId(?string)` / nullable `productId`.
3. `lib/Migration/Version1012Date20261013.php`:
   - `bbml_lists`: add `kind` (string 32, nullable), `is_active` (bool, default false).
   - `bbml_list_items`: make `product_id` nullable.
   - `postSchemaChange`: backfill `kind` (income → INCOME, subscription → SUBSCRIPTION,
     finished → PURCHASE, else NEED_TO_BUY) and activate the newest To Buy list per owner.
4. `appinfo/info.xml` version bump.
5. `tests/unit/**` for a serialization/derivation case.

## Outcome

Not started.
