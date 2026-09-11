---
created: 2026-09-11
type: spec
tags: [spec, ui, catalog, search, fuzzy, pagination, frontend]
related:
  - "../plans/catalog-search.md"
  - "../tickets/ticket-14-catalog-search-paging.md"
---

# Spec — Catalog search & paged lists

Part of [epic](../epics/nextcloud-web-app.md).

## Summary

Add a search field to each Catalog tab (Categories, Stores, Products,
Subscriptions, Income) that filters the items relevant to that tab, and page the
rendered results so the Products tab stays fast with hundreds of items. Matching
is fuzzy (typo-tolerant) and always runs over the **whole** tab data set, not the
currently rendered page.

## Requirements

- A search field is shown for the active tab, scoped to that tab:
  - Categories tab searches categories (name and parent name).
  - Stores tab searches stores (name and address).
  - Products/Subscriptions/Income tabs search the products of that tab
    (name, aliases, barcode).
- Search is **fuzzy**: typos should still match (e.g. `zbra` finds
  `Zebra cookies`).
- Search must cover **all** items in the tab, independent of what is currently
  rendered. If the user is looking at products starting with "A", searching
  `Zebra` must still surface "Zebra cookies".
- Results are ranked by relevance and rendered with a **"Load more"** page size
  (50) plus a "Showing X of Y" summary.
- The search term **resets when the active tab changes**.
- Categories render as a flat, filtered list while a search is active (instead
  of the tree), showing the parent name as context.
- "No matches" gets a dedicated empty state with a *Clear search* action,
  distinct from the "nothing created yet" empty state.
- Matched text is highlighted in the row.
- Create/edit/delete continue to work while a search is active.

## Scope

- **In:** client-side search + fuzzy ranking, paged rendering, empty states,
  highlighting, row component extraction.
- **Out:** server-side search API (no backend/schema changes), searching other
  views (e.g. shopping lists), search history, advanced query syntax, search
  over price history.

## Design decisions

- **Client-side full load + Fuse.js.** The Catalog already fetches all products
  (`fetchProducts('all')`) and all categories/stores on mount, so fuzzy search
  adds no extra payload. Fuzzy typo tolerance is impractical to do portably in
  SQL (MySQL/Postgres/SQLite), so it is done in the browser. This is
  appropriate for the stated scale (hundreds of items); `src/utils/search.ts`
  isolates the engine so a future server-side `q`/`limit`/`offset` endpoint can
  replace it without touching the UI.
- **Paging is a rendering concern only.** Search filters the full in-memory
  list; "Load more" only controls how many matches are drawn. Changing the query
  resets the render window to the top of the new result set.
- **Memoized Fuse indexes.** Indexes are rebuilt only when the underlying base
  array changes (initial load, create/edit/delete, product tab change) — never
  per keystroke.

## Constraints

- No new backend endpoint, mapper, migration or `openapi.json` change.
- Must keep the existing empty/error/retry states working.
- Browser-only feature; no PHP test impact.

## UI/UX considerations

- Search field placed under the tab bar; placeholder names the tab
  ("Search products…"). Trailing clear button when non-empty.
- Result summary announced via `aria-live="polite"`.
- Highlight uses `NcHighlight`; fuzzy matches that are not an exact substring may
  not highlight their exact characters.
- "Load more (N remaining)" button when more matches exist.

## Related

- [plan](../plans/catalog-search.md)
- [ticket-14](../tickets/ticket-14-catalog-search-paging.md)
