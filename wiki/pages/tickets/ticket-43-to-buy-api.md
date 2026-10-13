---
created: 2026-10-13
type: ticket
status: proposed
summary: T43 — To Buy list & item API: atomic active-list create, plain-text items, purchase guard
---

# T43 — To Buy list & item API

Backend for the [To Buy list redesign](../specs/to-buy-list-redesign.md)
([plan](../plans/to-buy-list-redesign.md)). Needs [T42](ticket-42-to-buy-data-model.md).

## Spec

Expose the active To Buy list and plain-text items over OCS: an atomic
deactivate-all + insert create (D-30), name-only items (D-29), and a guard that forbids
finalizing a To Buy list as a purchase (R4). Also serialize `kind`/`isActive`.

## Plan

1. `lib/Db/ListMapper.php` — `findActiveToBuyList(owner)`,
   `deactivateAllToBuyLists(owner)`, `insertActiveToBuyList(ListEntity)` (transaction).
2. `lib/Db/ListItemMapper.php` — serialize-friendly query + check-state update.
3. `lib/Controller/ListController.php`:
   - serialize `kind` (derived when null) + `isActive`;
   - `POST /api/lists/to-buy` (`prefix?`, `itemNames?: string[]`) — name
     `"$prefix dd.MM.yyyy"`, atomic deactivate+insert, carry-over items, return the list;
   - `GET /api/lists/to-buy` — active list or `{ list: null }`;
   - 422 when finalizing a `NEED_TO_BUY` list.
4. `lib/Controller/ListItemController.php` — plain-text create for To Buy lists
   (`customName` only; a supplied `productId` degrades to a name copy).
5. `tests/unit/Controller/{List,ListItem}ControllerTest.php`; `composer openapi`.

## Outcome

Not started.
