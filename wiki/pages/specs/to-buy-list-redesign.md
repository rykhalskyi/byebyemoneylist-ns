---
created: 2026-10-13
type: spec
status: proposed
summary: To Buy list redesign — parity spec for the Android NEED_TO_BUY model (plain-text planning lists, active list, dedicated card/screen/widget, LLM auto-check)
---

# Spec — To Buy list redesign (Android parity)

Port the Android "To Buy" redesign to the Nextcloud app. Source plans in the Android
repo (`~/Source/byebyemoneylist/wiki/pages/plans/`): `10-to-buy-list-redesign`,
`11-add-list-dialog-fab-redesign`, `12-to-buy-auto-check`, `13-to-buy-widget`,
`14-review-fixes-to-buy`.

Plan: [plans/to-buy-list-redesign](../plans/to-buy-list-redesign.md).
Tickets: [T42](../tickets/ticket-42-to-buy-data-model.md) →
[T43](../tickets/ticket-43-to-buy-api.md) →
[T44](../tickets/ticket-44-add-list-dialog-to-buy-card.md) →
[T45](../tickets/ticket-45-to-buy-filters-totals.md) →
[T46](../tickets/ticket-46-to-buy-screen.md) →
[T47](../tickets/ticket-47-to-buy-widget.md) →
[T48](../tickets/ticket-48-to-buy-auto-check.md).

## Summary

Android cleanly separates freeform planning notes (**To Buy**) from money-bearing
records (**Purchases / Income / Subscriptions**). To Buy lists have an auto-generated
localized name, hold plain-text items only (no product, price, store or category),
exactly one is *active*, and they never contribute to expense totals. This app has none
of that today: lists are `new | finished | archived` with catalog-bound, priced items
and no check-off UI.

## Parity matrix

| Feature | Android | Nextcloud today | Action |
|---|---|---|---|
| List discriminator | `ListKind` = NEED_TO_BUY / PURCHASE / INCOME / SUBSCRIPTION | `status` + `isIncome`/`isSubscription` booleans | add `kind` — D-28 |
| Active To Buy list | `isActive`, exactly one | none | add `is_active` — D-28, D-30 |
| To Buy items | plain text (`productId=0`, `customName`, qty 1, no price) | `product_id` NOT NULL, priced | nullable + `custom_name` — D-29 |
| Create UX | FAB **Add** → tabbed `AddListDialog` (To Buy/Subscription/Income) with carry-over | three header buttons; single `NewListDialog` form | new `AddListDialog` — R13 |
| To Buy card | cart icon, active badge, checkbox rows, "Add item", rename, delete-only menu, "Open list" | generic row + expand; add/delete; no check | dedicated rendering |
| Check-off | checkbox, 4 s undo | none | add check UI |
| Filters | All / To Buy / Purchases / Income / Subscriptions | year/month groups only | add chips |
| Screen | `ToBuyScreen` paper sheet, no bottom nav | none | new view |
| Widget | `WidgetData.ToBuy` (title, checked/total, preview ≤3) | none | new widget — D-32 |
| Auto-check | after purchase, LLM matches bought names → checks pending items | none | server-side service — D-31 |
| Totals | To Buy excluded | non-finished already excluded; purchase picker still includes them | exclude `kind` — D-28 |
| Purchase flow | always creates a new PURCHASE list | can finish an open `new` list by name | remove the open-list picker/match — R10 |
| Copy list | copies a To Buy list as To Buy, inactive | copy resets to `new` and skips plain-text items | preserve kind + plain text, inactive — D-39 |
| In-store mode | removed in the redesign | none | do not add — D-37 |
| Archive mode | removed in the redesign (`isArchived`) | `archived` status + label | remove `archived` — D-37 |

## Requirements

- **R1** — A list has a `kind`; To Buy lists are created with an auto title
  `"<localized To Buy> dd.MM.yyyy"` and become the only active one (D-30).
- **R2** — To Buy items are plain text: name only, quantity 1, no price/discount, never
  catalog-linked (D-29). The migration converts legacy open-list items to this shape
  (D-33).
- **R3** — Adding to a To Buy list is free text; picking a catalog suggestion copies the
  name only (no price step). Barcode/receipt-import flows do not apply to To Buy.
- **R4** — A To Buy list cannot be finalized as a purchase (server guard).
- **R5** — Exactly one active To Buy list; creating a new one may carry over unchecked
  items from the previous active list.
- **R6** — Item check/uncheck, rename (`custom_name`) and delete with an undo window.
- **R7** — Status filter chips: All / To Buy / Purchases / Income / Subscriptions.
- **R8** — A dedicated focused screen and a dashboard widget for the active To Buy list.
- **R9** — After **any** purchase finalization (list finish, receipt commit, approved
  import) with an active LLM profile, match bought names (receipt/entered non-coupon
  items, else the finished list's checked `quantity > 0` items) against pending To Buy
  items and check the matches (best-effort, silent, no separate consent prompt — Android
  D1).
- **R10** — To Buy lists are excluded from dashboard/analytics totals, and the purchase
  flow always creates a new PURCHASE list (no open-list picker or name match).
- **R11** — Archive and in-store modes are not part of the model (Android parity);
  legacy `archived` rows migrate to `finished`/PURCHASE (D-37).
- **R12** — Copying a To Buy list keeps it a To Buy list, copies plain-text items and
  creates the copy inactive (only create activates — D-39).
- **R13** — `AddListDialog` offers To Buy / Subscription / Income (no Purchase).
  Subscription = name + all categories + Week/Month/Year (default Month, recurring
  forced, start-empty false); Income = name + income categories + recurring/period/
  start-empty.

## Design decisions

- D-28 — explicit `kind` + `is_active`, backfilled from legacy flags.
- D-29 — nullable `product_id`; name in `custom_name`.
- D-30 — atomic active-list switch.
- D-31 — server-side auto-check (keys stay server-side).
- D-32 — widget stays in localStorage.
- D-33 — backfill existing open (`new`) lists to To Buy and convert their items to plain
  text (`custom_name` from the product name, `product_id` null, qty 1, no price/discount);
  finished/archived lists become PURCHASE (Android parity).
- D-37 — remove archive mode entirely and never introduce in-store mode (Android parity);
  legacy archived lists become PURCHASE.
- D-38 — auto-check is gated only on an active LLM profile, with no separate consent
  prompt (Android D1).
- D-39 — copied To Buy lists stay `NEED_TO_BUY`, copy plain-text items and are inactive;
  only `POST /api/lists/to-buy` activates a list.

## Intentional divergences from Android

- **`status` retained** — Android removed `isNew`/`isArchived`; this app keeps the
  existing `status` column for the open/finished distinction (`new` effectively means a
  To Buy list after migration) while `kind` carries the list type.
- **Sharing** — Android removed clipboard/share import; this app keeps its OCS list
  sharing. Copying a readable To Buy list is handled by D-39.
- **Widget storage** — widget layout stays in localStorage (D-32); refresh is on mount
  plus item changes rather than Room flows.

## Scope

In scope: data model + migration, To Buy list/item API, create dialog, card, check UI,
status filters, dedicated screen, dashboard widget, LLM auto-check, removal of the
`archived` status and archive/in-store remnants, en/uk/de strings.

Out of scope: in-store mode (removed in the Android redesign and never present here);
the Android file-based clipboard/share import (this app already has OCS sharing); barcode
scanning (not present here); the background Android sync API (T13).

## Constraints

- Nextcloud 31–35, PHP 8.1, AppFramework entities/mappers/migrations; OCS API +
  `openapi.json`.
- Vue 3 `<script setup>` + TS, `@nextcloud/vue`, `@mdi/js`; `t()` + `npm run l10n`.
- Widget layout stays in localStorage (D-06, D-32).

## Acceptance criteria

- [ ] Migration adds `kind`/`is_active`, makes `product_id` nullable, and converts legacy open items to plain text.
- [ ] `POST /api/lists/to-buy` creates the active list atomically and can carry over item names.
- [ ] Plain-text item create/check/rename/delete round-trips; a To Buy list cannot be finished.
- [ ] To Buy lists render a dedicated card; check-off, rename and delete+undo work.
- [ ] Status filters separate To Buy from purchases/income/subscriptions.
- [ ] Dedicated screen and dashboard widget show the active list, with empty states.
- [ ] Any purchase finalization (list, receipt commit, approved import) with an active profile checks matched pending items.
- [ ] To Buy lists contribute 0 to dashboard/analytics, are absent from the purchase flow (which always creates a new list), and copied To Buy lists stay To Buy/inactive.
- [ ] `AddListDialog` creates To Buy / Subscription / Income with the Android tab fields, and has no Purchase option.
- [ ] `archived` is removed from the client model/UI, legacy archived lists migrate to PURCHASE, and no in-store affordance exists.
- [ ] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`,
      `npm run lint`, `npm run stylelint`, `npm run test`, `npm run build`, `npm run l10n` pass.
