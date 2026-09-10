---
created: 2026-09-10
type: ticket
tags: [ticket, ui, catalog, stores, categories, address, frontend]
related:
  - "../epics/nextcloud-web-app.md"
---

# T11 — Stores: category chooser + address in dialog and list

Part of [epic](../epics/nextcloud-web-app.md). Source: GitHub issue #22.

## Summary

Surfaced the already-supported store `address` and `categoryIds` fields in the
Catalog Stores UI. Frontend-only: the backend (`bbml_stores.address`, the
`bbml_store_categories` junction, and `StoreController`) already accepted and
returned both fields.

## Description

- **Store dialog** (`NewStoreDialog.vue`): added an **Address** `NcTextField` and a
  multi-select **Category** `NcSelect` (`multiple` + `keepOpen`), loaded via
  `fetchCategories()`. Both prefill when editing and are included in the
  create/update payload (`address: string | null`, `categoryIds: string[]`).
- **Stores list** (`Catalog.vue`): the address shows as a pale, ellipsis-truncated
  subname; stores with a category get a colored left line using the first
  category's color (mirrors `product-row`).
- Existing stores without address/category render unchanged.

## Scope

- **In:** frontend types, dialog fields, list rendering/mark.
- **Out:** store merge/duplicate handling, backend changes.

## Acceptance criteria

- [x] Create/Edit dialog allows entering an address and selecting one or more categories.
- [x] Stores list shows the address in a pale, truncated style when long.
- [x] Stores with a category show a colored left line matching the category color.
- [x] Existing stores without address/category render unchanged.
- [x] `npm run lint` and `npm run build` pass.

## Files (changed)

- `src/types.ts` — `Store` gains `address`, `categoryIds`; `StorePayload` gains both
- `src/components/NewStoreDialog.vue` — address field + multi category select
- `src/views/Catalog.vue` — `storeCategories`/`storeMarkStyle`, address subname, `store-row` styling

## Status

**Implemented (2026-09-10).** `npm run lint`, `npm run stylelint`, `npm run build` pass.
