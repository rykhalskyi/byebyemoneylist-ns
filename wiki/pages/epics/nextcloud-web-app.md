---
created: 2026-08-26
type: epic
tags: [epic, nextcloud, web-app, roadmap]
related:
  - "../project-overview.md"
  - "../tickets/ticket-01-shopping-lists-page.md"
  - "../tickets/ticket-02-catalog-page.md"
  - "../tickets/ticket-06-products-tab.md"
  - "../tickets/ticket-07-add-product-to-list.md"
---

# Epic: Bye-Bye Money List Nextcloud App

## Summary

Build the **server side** of Bye-Bye Money List — a Nextcloud application providing multi-user storage, sync, sharing and permissions — together with an optional **web UI** for the Android shopping-list manager and expense tracker.

## Goals

- Store all data (shopping lists, purchases, categories, products, stores, price history) on the server.
- Sync with the Android app, replacing the current folder-based `*.bbl.json` file sync with a real server API/DAV endpoint (per-list `syncId`, per-user identity, timestamp-based conflict resolution).
- Enable multi-user usage: lists shared with another user or a group; expenses viewable per user or per whole group (family budget).
- Provide a web UI for lists, catalog, dashboard, analytics and settings.

## Source document

`~/Documents/Draft/bbml-nc-ideas.md` — technical task with domain model context from the `byebyemoneylist` Android repo.

## Ticket breakdown

Implementation order is tracked by numbered tickets (e.g. [ticket-01-shopping-lists-page](../tickets/ticket-01-shopping-lists-page.md), [ticket-02-catalog-page](../tickets/ticket-02-catalog-page.md)); a ticket may cover parts of several T-rows below.

| ID | Ticket | Source section | Status |
|----|--------|----------------|--------|
| T1 | Shopping Lists page (view lists + add a new list) — [ticket-01](../tickets/ticket-01-shopping-lists-page.md) | §2 Shopping Lists | **Done** |
| T2 | List detail page — items, add/check/edit, product mapping, review flow | §2.16 Review flow | partial — add product to list (search + create new + price/quantity) done in [ticket-07](../tickets/ticket-07-add-product-to-list.md); check/edit/delete/review open |
| T3 | Purchase & lifecycle — finish list (date + `finalTotal`), statuses new/finished/archived, empty list w/ price, quick purchase | §2.2, §2.3, §2.4, §2.13, §2.15 | open |
| T4 | Catalog — Categories tab (CRUD, hierarchy, color/emoji, income flag) | §5.1 | partial — display + create done in [ticket-02](../tickets/ticket-02-catalog-page.md); edit/delete open |
| T5 | Catalog — Stores tab (CRUD, merge duplicates) | §5.2 | partial — display + create done in [ticket-02](../tickets/ticket-02-catalog-page.md); edit/merge open |
| T6 | Catalog — Products tab (CRUD, barcode, aliases, favorites, status, merge, price history) | §5.3, §5.6, §5.7 | partial — display + create done in [ticket-06](../tickets/ticket-06-products-tab.md); edit/delete/merge/price history open |
| T7 | Subscriptions & Income — dedicated tabs/views, list flags | §2.10, §2.11, §5.4, §5.5 | open |
| T8 | Recurring lists — WEEK/MONTH/YEAR period, forward empty/with items, auto-archive + create | §2.9 | open |
| T9 | Dashboard — user-managed widgets (spent today, this month, category spending, quick purchase, scan) | §3 | open |
| T10 | Analytics — period picker, overview charts, product stats, budget hints, PDF report, AI assistant semantics | §4 | open |
| T11 | Settings — currency, actual-price rule, dashboard toggle, LLM/AI profiles, CSV export, share identity, version/privacy | §6 | open |
| T12 | Multi-user, sharing & ownership — user/group sharing, permissions, owner-only ops, group budget view | §1 | open |
| T13 | Server sync API — real API/DAV endpoint replacing folder sync, conflict resolution, access control, product matching | §7 | open |

## Out of scope / open questions

- Full web UI for everything vs. backend/sync layer only (decision: build web UI incrementally, sync API in T13).
- Server-side analytics aggregation for group reports (candidate for a later ticket).
- Explicit budget planning (per-month/per-category budget targets) — new feature not in Android yet.
- Nextcloud notifications for shared-list changes or recurring-list rollover.
