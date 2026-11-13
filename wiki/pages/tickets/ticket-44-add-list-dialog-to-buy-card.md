---
created: 2026-10-13
type: ticket
status: implemented
summary: T44 — Add list dialog, dedicated To Buy card and item check/rename/delete UI
---

# T44 — Add list dialog, To Buy card & check UI

Frontend for the [To Buy list redesign](../specs/to-buy-list-redesign.md)
([plan](../plans/to-buy-list-redesign.md)). Needs [T43](ticket-43-to-buy-api.md).

## Spec

Replace the **Add income** and **Add list** header buttons with **Add list** (tabbed
`AddListDialog`, offering To Buy / Subscription / Income only — purchase lists are created
by the purchase flow, not here) and keep **Add purchase**, and give To Buy lists a
dedicated card with checkbox rows, rename, delete and an "Add item" action (R1, R3, R5,
R6). This also introduces the app's first check-off UI.

## Plan

1. `src/types.ts` — `kind`/`isActive` on `ShoppingList`; nullable `productId` +
   `customName` on `ListItem`; drop `archived` from `ListStatus` (D-37).
2. `src/services/listsApi.ts` — `createToBuyList(prefix, itemNames)`,
   `fetchActiveToBuyList()`, `addToBuyItem(listId, name)`.
3. `src/utils/listDisplay.ts` — kind derivation + To Buy labels/icons; remove the
   `archived` label/variant (D-37).
4. `src/components/AddListDialog.vue` (new) — To Buy / Subscription / Income selector
   (matching Android, no Purchase option), default To Buy.
   - To Buy: no fields, carry-over checkboxes.
   - Subscription: name + **all** categories + Week/Month/Year interval (default Month),
     recurring forced, `isForwardEmpty = false`.
   - Income: name + income categories + recurring/period/start-empty.
   - Name required for Subscription/Income.
5. `src/views/ShoppingLists.vue` — wire Add list / Add purchase and the To Buy actions.
6. `src/components/shoppinglists/ShoppingListRow.vue` — dedicated To Buy card (cart icon,
   Active chip, no store/date/category/price, checkbox rows with strikethrough + muted
   name, rename on tap, "Add item", Open list/Delete menu).
7. `src/components/shoppinglists/AddToBuyItemDialog.vue`,
   `EditToBuyItemDialog.vue` (new); `ShoppingListItems.vue` checkbox rows.
8. Tests: `tests/js/**` dialog + card specs; update
   `tests/js/utils/listDisplay.spec.ts` to drop the `archived` cases (D-37).

## Outcome

Implemented on `feature/to-buy-list-redesign`:

- New `AddListDialog` (vertical To Buy / Subscription / Income selector, no Purchase):
  To Buy creates the active list with optional carry-over of unchecked items;
  Subscription = name + all categories + interval (default Monthly, recurring forced,
  start-empty false); Income = name + income categories + recurring/period/start-empty.
- `ShoppingLists` header is now **Add list** + **Add purchase**; `NewListDialog` removed.
- To Buy card: Active chip, date-only subtitle, plain-text checkbox rows with
  strikethrough, rename on tap, delete, "Add item", and an Open list/Delete menu.
- New `AddToBuyItemDialog` and `EditToBuyItemDialog`; To Buy add/check/rename/delete
  wired through the composable.
- Client model: `kind`/`isActive`, nullable `productId`, `customName`, dropped `archived`;
  `listDisplay` kind helpers; l10n en/de/uk updated.
- "Open list" currently expands the card; T46 will point it at the dedicated screen.

Vitest 237 green; eslint, stylelint and production build clean.
