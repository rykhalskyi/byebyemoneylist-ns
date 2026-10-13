---
created: 2026-10-13
type: ticket
status: proposed
summary: T44 — Add list dialog, dedicated To Buy card and item check/rename/delete UI
---

# T44 — Add list dialog, To Buy card & check UI

Frontend for the [To Buy list redesign](../specs/to-buy-list-redesign.md)
([plan](../plans/to-buy-list-redesign.md)). Needs [T43](ticket-43-to-buy-api.md).

## Spec

Replace the three header buttons with **Add list** (tabbed `AddListDialog`) + **Add
purchase**, and give To Buy lists a dedicated card with checkbox rows, rename, delete and
an "Add item" action (R1, R3, R5, R6). This also introduces the app's first check-off UI.

## Plan

1. `src/types.ts` — `kind`/`isActive` on `ShoppingList`; nullable `productId` +
   `customName` on `ListItem`.
2. `src/services/listsApi.ts` — `createToBuyList(prefix, itemNames)`,
   `fetchActiveToBuyList()`, `addToBuyItem(listId, name)`.
3. `src/utils/listDisplay.ts` — kind derivation + To Buy labels/icons.
4. `src/components/AddListDialog.vue` (new) — To Buy / Subscription / Income selector,
   default To Buy, no-field To Buy tab with carry-over checkboxes.
5. `src/views/ShoppingLists.vue` — wire Add list / Add purchase and the To Buy actions.
6. `src/components/shoppinglists/ShoppingListRow.vue` — dedicated To Buy card (cart icon,
   Active chip, no price/category/store, checkbox rows, "Add item", Open list/Delete menu).
7. `src/components/shoppinglists/AddToBuyItemDialog.vue`,
   `EditToBuyItemDialog.vue` (new); `ShoppingListItems.vue` checkbox rows.
8. Tests: `tests/js/**` dialog + card specs.

## Outcome

Not started.
