---
created: 2026-10-02
type: ticket
status: proposed
summary: T38 — Attribute shared-list expenses to the purchaser (analytics + dashboard)
---

# T38 — Purchaser attribution for shared lists

Part of [epic](../epics/nextcloud-web-app.md) (T12). Design: [specs/list-sharing](../specs/list-sharing.md).

## Spec

Expenses and analytics must count toward the user who made the purchase, not the list
owner. Today `AnalyticsMapper::overview` and `DashboardMapper::applyBaseFilters`
aggregate by `bbml_lists.owner`.

- Adopt Option A: per-viewer totals from items in finished/non-subscription/non-income
  lists where `bbml_list_items.owner = viewer`; `final_total` applies only to lists
  with no priced items.
- Applies to both analytics and dashboard widgets for consistency.
- No double counting: a shared list contributes each purchaser's own items.

## Plan

1. `lib/Db/AnalyticsMapper.php` — item-level aggregation keyed by `li.owner`.
2. `lib/Db/DashboardMapper.php` — same attribution.
3. Keep list-level fallback for exotic lists without item prices.
4. Tests: shared list split across two purchasers; fallback path; existing single-user
   totals unchanged.

## Outcome

Not started.
