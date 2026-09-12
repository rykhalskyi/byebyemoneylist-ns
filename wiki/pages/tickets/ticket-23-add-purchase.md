---
created: 2026-09-12
type: ticket
status: implemented
summary: T3 partial — Add purchase (manual) finishes a new list with a final total
---

# T3 (partial) — Add purchase

Part of [epics/nextcloud-web-app](../epics/nextcloud-web-app.md).

## Spec

Add an **Add purchase** action next to **Add list** on the Shopping Lists page. The
dialog records a purchase as a finished list carrying a `finalTotal`:

- List name is an editable select: pick one of the current user's `new` lists, or
  type a name for a new list.
- Two tabs: **Manual input** and **Scan receipt**. Scan receipt is a placeholder.
- Manual input: store (editable select, auto-created when new), total price
  (prefilled with the sum of the selected list's item prices), and a single
  required category (expense categories only).
- Saving an existing `new` list sets its `finalTotal`, `purchaseDate` (now) and
  moves it to `finished`. Saving a new name creates an empty finished list with the
  price.

Scope: frontend only. The existing OCS API already accepts `finalTotal`,
`purchaseDate`, `isFinished`, `storeId` and `categoryIds` on both list create and
update, so no backend change is needed.

## Plan

1. `src/types.ts` — extend `ListPayload` with `categoryIds`, `finalTotal`,
   `purchaseDate`, `isFinished`.
2. `src/services/listsApi.ts` — add `updateList(id, payload)` (`PUT /api/lists/{id}`).
3. `src/utils/purchase.ts` (new) — `parsePrice`, `itemsTotal`, `findNewListByName`.
4. `src/components/PurchaseDialog.vue` (new) — dialog per the spec, emitting
   `saved(list)`.
5. `src/views/ShoppingLists.vue` — header button + dialog wiring; `onPurchaseSaved`
   replaces/prepends the list and invalidates its cached items.
6. `l10n/{en,de,uk}.json` — new strings; `npm run l10n`.
7. `tests/js/utils/purchase.spec.ts` (new).

## Outcome

Implemented (2026-09-12).

- Existing `new` lists are updated via `PUT` with `isFinished=true`,
  `finalTotal`, `purchaseDate=now` and `categoryIds=[categoryId]`; new names are
  created via `POST` with the same fields (empty list, no items).
- Store is a taggable `NcSelect`; a typed name that does not match an existing
  store is created through `POST /api/stores` on save.
- Total price is prefilled from `fetchListItems` (sum of `price × quantity` over
  priced items) because the server `totalPrice` only sums *checked* items.
- Scan receipt tab shows a placeholder.
- Leaving the list name empty generates `StoreName + date` (locale date) on save,
  so the name is never a blocking validation (`defaultListName` in
  `src/utils/purchase.ts`).
- Verified: `npm run lint` (new/changed files clean), `npm run stylelint`,
  `npm run test` (57 tests), `npm run build`, `npm run l10n`.
