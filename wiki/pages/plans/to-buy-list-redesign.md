---
created: 2026-10-13
type: plan
status: in-progress
summary: To Buy list redesign implementation plan — seven tickets from data model/migration to LLM auto-check
---

# Plan — To Buy list redesign (Android parity)

Implements [specs/to-buy-list-redesign](../specs/to-buy-list-redesign.md).
Seven tickets: [T42](../tickets/ticket-42-to-buy-data-model.md) →
[T43](../tickets/ticket-43-to-buy-api.md) →
[T44](../tickets/ticket-44-add-list-dialog-to-buy-card.md) →
[T45](../tickets/ticket-45-to-buy-filters-totals.md) →
[T46](../tickets/ticket-46-to-buy-screen.md) →
[T47](../tickets/ticket-47-to-buy-widget.md) →
[T48](../tickets/ticket-48-to-buy-auto-check.md).

## Order and dependencies

```
T42 (model+migration) ── T43 (list/item API) ──┬── T44 (dialog + card + check UI)
                                               ├── T45 (filters + totals exclusion)
                                               ├── T46 (dedicated screen)
                                               └── T47 (dashboard widget)
T48 (LLM auto-check) needs T43; independent of T44–T47
```

T42 and T43 are prerequisites for everything else. T44–T47 can proceed in parallel once
T43 lands. T48 only needs the list/item API plus an active LLM profile to test against.

## Step-by-step

### T42 — Data model & migration (backend first)

1. `lib/Entity/ListEntity.php` — add `kind` (`?string`, default null) and `isActive`
   (`?bool`, default false); `addType` the boolean.
2. `lib/Entity/ListItemEntity.php` — allow `productId` to be null (`setProductId(?string)`).
3. `lib/Migration/Version1013Date20261112.php` (new) — `changeSchema`:
   - `bbml_lists`: `addColumn('kind', Types::STRING, ['length' => 32, 'notnull' => false])`,
     `addColumn('is_active', Types::BOOLEAN, ['notnull' => true, 'default' => false])`.
   - `bbml_list_items`: `modifyColumn('product_id', ['notnull' => false])`.
   - `postSchemaChange` (inject `IDBConnection`, like `Version1006`): normalize legacy
     archive rows (`status` `archived` → `finished`, keeping the purchase data), backfill
     `kind` (`is_income` → INCOME, `is_subscription` → SUBSCRIPTION, `status` `finished`
     → PURCHASE, else NEED_TO_BUY), activate the newest To Buy list per owner only when
     none is active yet, then convert To Buy items to plain text (D-33):
     `custom_name = COALESCE(custom_name, product.name)`, `product_id = NULL`,
     `quantity = 1.0`, `price = NULL`, `discount = NULL`.
4. `appinfo/info.xml` — bump version; `composer openapi` after the API changes (T43).

### T43 — List & item API

5. `lib/Db/ListMapper.php` — `findActiveToBuyList(owner)`,
   `deactivateAllToBuyLists(owner)`, and `insertActiveToBuyList(ListEntity)`
   (deactivate + insert in one `beginTransaction()`), mirroring `insertActiveToBuyList`
   on the Android DAO.
6. `lib/Controller/ListController.php`:
   - `serializeList` returns `kind` (derived when null via
     `isIncome`/`isSubscription`/`isFinished`, like Android `ListKind.derive`) and `isActive`.
   - `POST /api/lists/to-buy` — params `prefix?`, `itemNames?: string[]`; name
     `"$prefix dd.MM.yyyy"` (client passes the localized `t('To Buy')`), atomic
     deactivate+insert, then insert carry-over plain-text items; returns the list.
   - `GET /api/lists/to-buy` — the active To Buy list (or `{ list: null }`).
   - Guard in `update`/`create`: finalizing (`isFinished = true`) a `NEED_TO_BUY` list
     returns 422.
7. `lib/Controller/ListItemController.php` — when the list `kind` is `NEED_TO_BUY`,
   accept `customName` with no `productId` (store null product, quantity 1.0, no
   price/discount); if a `productId` is supplied, store the resolved product name as
   `customName` (name copy, no link). Serialize `productName` from `customName` for To Buy.
8. `tests/unit/Controller/ListControllerTest.php`,
   `tests/unit/Controller/ListItemControllerTest.php` — new cases.

### T44 — Add dialog, To Buy card & check UI (frontend)

9. `src/types.ts` — add `kind`, `isActive` to `ShoppingList`; make `ListItem.productId`
   nullable and surface `customName`; drop `archived` from `ListStatus`.
10. `src/services/listsApi.ts` — `createToBuyList(prefix, itemNames)`,
    `fetchActiveToBuyList()`, `addToBuyItem(listId, name)`; reuse `updateListItem` for
    check/rename.
11. `src/utils/listDisplay.ts` — derive kind (fallback from
    `isIncome`/`isSubscription`/`isFinished`, Android `ListKind.derive`), helper
    labels/icons and `isToBuy`/`isToBuyActive`; remove the `archived` label/variant
    (D-37).
12. `src/components/AddListDialog.vue` (new) — vertical type selector To Buy /
    Subscription / Income (default To Buy), matching Android; **no Purchase option**
    because purchase lists are created by the purchase flow.
    - To Buy: no fields; carry-over checkboxes of the active list's unchecked items;
      confirm creates the list.
    - Subscription: name + **all** categories + interval Week/Month/Year (default Month);
      recurring forced on, `isForwardEmpty = false`; name required.
    - Income: name + income categories + recurring checkbox + period + start-empty;
      name required.
13. `src/views/ShoppingLists.vue` — fold the **Add income** and **Add list** header
    buttons into **Add list** (`AddListDialog`), keep **Add purchase**; wire
    create/rename/check/delete for To Buy.
14. `src/components/shoppinglists/ShoppingListRow.vue` — dedicated To Buy rendering:
    cart icon, "Active" chip, no store/date/category/price/status; checkbox rows with
    strikethrough + muted name when checked, rename on tap, delete; action button
    "Add item"; menu = Open list + Delete only.
15. `src/components/shoppinglists/AddToBuyItemDialog.vue`,
    `EditToBuyItemDialog.vue` (new); `ShoppingListItems.vue` — checkbox rows for To Buy.
16. `tests/js/**` — dialog + card component specs.

### T45 — Filters & totals exclusion

17. `src/views/ShoppingLists.vue` — status filter chips All / To Buy / Purchases /
    Income / Subscriptions; filter the grouped lists by derived kind.
18. `src/components/PurchaseDialog.vue` + `src/utils/purchase.ts` — purchases always
    **create a new PURCHASE list** (Android parity): drop the `newLists` picker and the
    `findNewListByName` "finish an existing open list" match/update path, so a typed name
    only names the new list and can never target a To Buy list (the server guard would
    reject it anyway).
19. `lib/Db/DashboardMapper.php`, `lib/Db/AnalyticsMapper.php` — add
    `kind != 'NEED_TO_BUY'` (defensive; non-finished already excluded).
20. `src/utils/listGroups.ts` — keep To Buy out of finished totals (no change expected;
    verify).

### T46 — Dedicated To Buy screen

21. `src/views/ToBuy.vue` (new) — paper surface, own header + back, quick-add row pinned
    at the bottom (IME "Done" adds and clears for rapid entry), check / rename on tap /
    delete with a 4 s undo, checked rows struck through + muted in insertion order,
    empty-list hint and no-active-list create CTA.
22. `src/App.vue` — a `toBuy` view state that hides `Menu` while active (the "no bottom
    nav" equivalent); the card's "Open list" and the widget open it.
23. `tests/js/**` — screen state spec.

### T47 — Dashboard widget

24. `src/constants/dashboardWidgets.ts` — add `toBuy` type + icon/label.
25. `src/components/dashboard/widgets/ToBuyWidget.vue` (new) — title,
    `checked/total`, first ≤3 unchecked preview names; tapping opens the To Buy screen;
    empty state (no active list) shows a create CTA; refresh when list items change
    (Android observes item changes) and on view mount.
26. `src/components/dashboard/AddWidgetDialog.vue` + `src/views/Dashboard.vue` — offer
    and render the widget; open it via the `App.vue` toBuy intent.

### T48 — LLM auto-check after purchase

27. `lib/Service/ToBuy/ToBuyAutoMatchService.php` (new) — gate on the active LLM profile
    (`DefaultLlmProfileService`) only (no separate consent prompt, Android D1); prompt to
    map bought names to pending To Buy item ids (`matchedIds`); accept only ids from the
    supplied pending set; swallow ordinary failures but let request cancellation propagate;
    re-verify list + unchecked before writing.
28. Hook into **every purchase finalization**: `lib/Controller/ListController.php` (create
    or update when `isFinished` becomes true), `lib/Service/Receipt/ReceiptCommitService.php`
    and `lib/Service/Import/PurchaseImportApproveService.php`. Resolve purchased names as
    Android does: receipt/entered items (non-coupon) when present, else the finished list's
    remaining checked items with `quantity > 0`; a manual total-only list yields nothing.
    Then check matches via `ListItemMapper`.
29. `tests/unit/Service/ToBuy/ToBuyAutoMatchServiceTest.php` (new).

### Cross-cutting — Localization & docs

30. `l10n/{en,de,uk}.json` + `npm run l10n` — `To Buy`, `Add item`, `Add to list`,
    `Item name`, `Open list`, `Active`, `Filter: To Buy`, `Filter: Purchases`, progress,
    hints, carry-over, widget, auto-check strings (added alongside each ticket, verified
    here); drop the obsolete `Archived` string (D-37).
31. Update [project-overview](../project-overview.md) domain model; link the tickets
    from the [epic](../epics/nextcloud-web-app.md).
32. `lib/Service/Sharing/ListCopyService.php` — copying a To Buy list preserves
    `kind = NEED_TO_BUY`, copies its items as plain text (`productId` null, `customName`,
    quantity 1, no price/discount) and creates the copy with `isActive = false` (Android
    parity: only `createToBuyList()` activates a list).

## Files

New:

- Backend: `lib/Migration/Version1013Date20261112.php`,
  `lib/Service/ToBuy/ToBuyAutoMatchService.php`,
  `tests/unit/Controller/{List,ListItem}ControllerTest.php`,
  `tests/unit/Service/ToBuy/ToBuyAutoMatchServiceTest.php`.
- Frontend: `src/components/AddListDialog.vue`,
  `src/components/shoppinglists/AddToBuyItemDialog.vue`,
  `src/components/shoppinglists/EditToBuyItemDialog.vue`, `src/views/ToBuy.vue`,
  `src/components/dashboard/widgets/ToBuyWidget.vue`, `tests/js/**`.

Modified:

- `lib/Entity/{List,ListItem}Entity.php`, `lib/Db/{List,ListItem,Dashboard,Analytics}Mapper.php`,
  `lib/Controller/{List,ListItem}Controller.php`,
  `lib/Service/Receipt/ReceiptCommitService.php`,
  `lib/Service/Import/PurchaseImportApproveService.php`,
  `lib/Service/Sharing/ListCopyService.php`, `appinfo/info.xml`, `openapi.json`.
- `src/types.ts`, `src/services/listsApi.ts`, `src/utils/{listDisplay,purchase}.ts`,
  `src/views/{ShoppingLists,Dashboard}.vue`, `src/App.vue`,
  `src/components/shoppinglists/{ShoppingListRow,ShoppingListItems}.vue`,
  `src/components/PurchaseDialog.vue`, `src/constants/dashboardWidgets.ts`,
  `src/components/dashboard/AddWidgetDialog.vue`, `l10n/{en,de,uk}.json`.

## Migrations

`Version1013Date20261112` — add `bbml_lists.kind` + `bbml_lists.is_active`; make
`bbml_list_items.product_id` nullable; in `postSchemaChange` normalize archived rows,
backfill `kind`/`is_active` and convert To Buy items to plain text (D-28, D-33).

## Testing checklist

- [x] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`.
- [x] `npm run lint`, `npm run stylelint`, `npm run test`, `npm run build`, `npm run l10n`.
- [x] `composer openapi` regenerates `openapi.json`.
- [ ] Migration dry-run on a dev instance with existing lists; verify kinds/active flag and that To Buy items become plain text (name kept, product link/price/discount cleared).
- [ ] Manual smoke: create a To Buy list; add/check/rename/delete items (+undo); carry over items into a new list; filters; dedicated screen; widget; finalize a purchase with an active profile and confirm matches get checked; verify To Buy lists never affect dashboard/analytics or the purchase flow; verify no archive or in-store affordance remains.

## Risks & mitigations

- **Backfill semantics** — legacy `new` lists may hold priced purchase-prep items; the
  migration converts them to plain-text To Buy items (Android parity, D-33). Safe here
  because there is no production data yet; still dry-run the migration on a dev instance.
- **Dual source of truth** — `kind` vs `isIncome`/`isSubscription` booleans can drift. Derive missing `kind` from flags on read; keep booleans authoritative for income/subscription until a later cleanup.
- **Cross-boundary atomicity** — create + carry-over spans HTTP; mitigated by accepting `itemNames[]` in the create request so the server does it in one transaction.
- **Widget reactivity** — localStorage widgets do not live-update across tabs like Android Room flows; acceptable (D-06/D-32). Refresh on view mount.
- **Silent server-side LLM call** — gated only on an active profile (matches Android D1); review as a privacy decision before shipping.
- **Plain-text item compatibility** — making `product_id` nullable touches item serialization, catalog joins (`AnalyticsMapper` left-joins products) and `reassignProduct`; audit those call sites.
