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
| Create UX | FAB **Add** → tabbed `AddListDialog` (To Buy/Subscription/Income) with carry-over | three header buttons; single `NewListDialog` form | new `AddListDialog` |
| To Buy card | cart icon, active badge, checkbox rows, "Add item", rename, delete-only menu, "Open list" | generic row + expand; add/delete; no check | dedicated rendering |
| Check-off | checkbox, 4 s undo | none | add check UI |
| Filters | All / To Buy / Purchases / Income / Subscriptions | year/month groups only | add chips |
| Screen | `ToBuyScreen` paper sheet, no bottom nav | none | new view |
| Widget | `WidgetData.ToBuy` (title, checked/total, preview ≤3) | none | new widget — D-32 |
| Auto-check | after purchase, LLM matches bought names → checks pending items | none | server-side service — D-31 |
| Totals | To Buy excluded | non-finished already excluded; purchase picker still includes them | exclude `kind` — D-28 |

## Requirements

- **R1** — A list has a `kind`; To Buy lists are created with an auto title
  `"<localized To Buy> dd.MM.yyyy"` and become the only active one (D-30).
- **R2** — To Buy items are plain text: name only, quantity 1, no price/discount, never
  catalog-linked (D-29).
- **R3** — Adding to a To Buy list is free text; picking a catalog suggestion copies the
  name only (no price step). Barcode/receipt-import flows do not apply to To Buy.
- **R4** — A To Buy list cannot be finalized as a purchase (server guard).
- **R5** — Exactly one active To Buy list; creating a new one may carry over unchecked
  items from the previous active list.
- **R6** — Item check/uncheck, rename (`custom_name`) and delete with an undo window.
- **R7** — Status filter chips: All / To Buy / Purchases / Income / Subscriptions.
- **R8** — A dedicated focused screen and a dashboard widget for the active To Buy list.
- **R9** — After finalizing a purchase with an active LLM profile, match bought names
  against pending To Buy items and check the matches (best-effort, silent).
- **R10** — To Buy lists are excluded from dashboard/analytics totals and from the
  purchase list picker.

## Design decisions

- D-28 — explicit `kind` + `is_active`, backfilled from legacy flags.
- D-29 — nullable `product_id`; name in `custom_name`.
- D-30 — atomic active-list switch.
- D-31 — server-side auto-check (keys stay server-side).
- D-32 — widget stays in localStorage.
- D-33 — backfill existing open lists to To Buy without rewriting item prices.

## Scope

In scope: data model + migration, To Buy list/item API, create dialog, card, check UI,
status filters, dedicated screen, dashboard widget, LLM auto-check, en/uk/de strings.

Out of scope: the Android file-based clipboard/share import (this app already has OCS
sharing); barcode scanning (not present here); the background Android sync API (T13).

## Constraints

- Nextcloud 31–35, PHP 8.1, AppFramework entities/mappers/migrations; OCS API +
  `openapi.json`.
- Vue 3 `<script setup>` + TS, `@nextcloud/vue`, `@mdi/js`; `t()` + `npm run l10n`.
- Widget layout stays in localStorage (D-06, D-32).

## Acceptance criteria

- [ ] Migration adds `kind`/`is_active` and makes `product_id` nullable; backfill leaves item data intact.
- [ ] `POST /api/lists/to-buy` creates the active list atomically and can carry over item names.
- [ ] Plain-text item create/check/rename/delete round-trips; a To Buy list cannot be finished.
- [ ] To Buy lists render a dedicated card; check-off, rename and delete+undo work.
- [ ] Status filters separate To Buy from purchases/income/subscriptions.
- [ ] Dedicated screen and dashboard widget show the active list, with empty states.
- [ ] Finalizing a purchase with an active profile checks matched pending items.
- [ ] To Buy lists contribute 0 to dashboard/analytics and are absent from the purchase picker.
- [ ] `composer lint`, `composer cs:check`, `composer psalm`, `composer test:unit`,
      `npm run lint`, `npm run stylelint`, `npm run test`, `npm run build`, `npm run l10n` pass.
