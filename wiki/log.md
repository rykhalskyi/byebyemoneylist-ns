# Wiki Log

## [2026-08-26] init | Wiki created
## [2026-08-26] epic | Bye-Bye Money List Nextcloud App
## [2026-08-26] ticket | T1 — Shopping Lists page
## [2026-08-26] update | wiki conventions — switched wikilinks to relative markdown links, made frontmatter YAML-safe
## [2026-08-26] update | ticket-01-shopping-lists-page — list id is a random server-generated UUID
## [2026-08-26] update | ticket-01-shopping-lists-page — enriched from source §2 (owner, store/categories, empty list with price)
## [2026-08-26] spec | Shopping Lists page
## [2026-08-26] plan | Shopping Lists page
## [2026-08-26] update | spec + ticket-01 — store/category as FKs, tables/entities in T1, read-only endpoints
## [2026-08-26] ticket | T1 — Shopping Lists page (implemented, verified on nextcloud.local)
## [2026-08-26] build-deploy | debugging — added debugging guide page
## [2026-08-27] spec | Catalog page (Categories + Stores tabs)
## [2026-08-27] plan | Catalog page (Categories + Stores tabs)
## [2026-08-27] ticket | T2 — Catalog page (Categories + Stores tabs)
## [2026-08-28] spec | Products tab (Catalog page)
## [2026-08-28] plan | Products tab (Catalog page)
## [2026-08-28] ticket | T6 — Products tab (display + create)
## [2026-08-28] spec | Add products to a shopping list
## [2026-08-28] plan | Add products to a shopping list
## [2026-08-28] ticket | T2 (partial) — Add products to a shopping list
## [2026-08-28] update | ticket-07-add-product-to-list — code-review fixes (findByIds name resolution, price/quantity bounds+rounding, transaction hardening, valid list markup, dialog retry/error states)
## [2026-08-29] spec | Shopping List Improvements (Issue #8)
## [2026-08-29] plan | Shopping List Improvements (Issue #8)
## [2026-08-29] ticket | Issue #8 — Shopping List Improvements
## [2026-09-05] ticket | T9 — List mirror API (shopping-list sync, server)
## [2026-09-05] update | ticket-09-list-mirror-api — PR #18 review fixes (finalTotal null-clear on PUT, strict UTC ISO-8601 date parsing, blank dates as absent, removed unused ListMapper::deleteCategoriesByCategoryId)
## [2026-09-10] ticket | T10 — Catalog: confirmation dialog on deletion
## [2026-09-10] ticket | T11 — Stores: category chooser + address in dialog and list
## [2026-09-10] ticket | T12 — Products: last price + info dialog with price history
## [2026-09-10] ticket | T13 — Products: image upload, deletion and display
## [2026-09-11] spec | Catalog per-tab fuzzy search + paged lists (T14)
## [2026-09-11] plan | Catalog per-tab fuzzy search + paged lists (T14)
## [2026-09-11] ticket | T14 — Catalog per-tab fuzzy search + paged lists
## [2026-09-11] spec | Frontend localization (i18n)
## [2026-09-11] plan | Frontend localization (i18n)
## [2026-09-11] ticket | T15 — L10n infrastructure & build wiring
## [2026-09-11] ticket | T16 — Localize app shell
## [2026-09-11] ticket | T17 — Localize dialogs
## [2026-09-11] ticket | T18 — Localize catalog components
## [2026-09-11] ticket | T19 — Locale-aware formatting & plurals
## [2026-09-11] ticket | T20 — German + Ukrainian translations & parity check
## [2026-09-11] ticket | T21 — Localization docs & wiki sync
## [2026-09-11] update | build-deploy — documented npm run l10n workflow
## [2026-09-11] update | project-overview — added localization stack
## [2026-09-11] update | wiki workflow — slim AGENTS.md, added SCHEMA.md, decisions.md and scripts/wiki.mjs (index/log/decision/lint), single-page tickets
## [2026-09-12] ticket | T22 — Group shopping lists by year and month
## [2026-09-12] update | ticket-22-list-grouping — year/month headers span full page width
## [2026-09-12] update | ticket-22-list-grouping — chevrons rotate in place
## [2026-09-12] update | ticket-22-list-grouping — header order label/sum/chevron, chevron far right
## [2026-09-12] update | ticket-22-list-grouping — full page width header (override core button specificity)
## [2026-09-12] ticket | T23 — Add purchase (manual) finishes a new list with a final total
## [2026-09-12] update | epic nextcloud-web-app — T3 marked partial; ticket-23 linked
## [2026-09-12] update | ticket-23-add-purchase — list name defaults to StoreName + date when left empty
## [2026-09-13] spec | Dashboard (T9)
## [2026-09-13] plan | Dashboard (T9)
## [2026-09-13] ticket | T9.1 — Dashboard shell (client-side layout)
## [2026-09-13] ticket | T9.2 — Dashboard spending API
## [2026-09-13] ticket | T9.3 — Dashboard data widgets
## [2026-09-13] ticket | T9.4 — Dashboard action widgets
## [2026-09-13] ticket | T9.5 — Dashboard info widget + version
## [2026-09-13] update | epic nextcloud-web-app — T9 split into T9.1–T9.5, linked spec/plan
## [2026-09-13] ticket | T9.1 — Dashboard shell (client-side layout) implemented
## [2026-09-13] ticket | T9.2 — Dashboard spending API implemented
## [2026-09-13] ticket | T9.3 — Dashboard data widgets implemented
## [2026-09-13] ticket | T9.4 — Dashboard action widgets implemented
## [2026-09-13] ticket | T9.5 — Dashboard info widget + app version implemented
## [2026-09-14] ticket | Plan T29 scan receipt in Purchase dialog (LLM OCR, review, commit)
## [2026-09-14] ticket | T29 scan receipt — implemented backend phases 1-2 (scan + commit endpoints/services)
## [2026-09-15] ticket | T29 scan receipt — implemented frontend scan tab (image normalization, scan/commit API, one-form result, Save receipt)
## [2026-09-15] ticket | T29 scan receipt — Phase 4: shopping-list receipt viewer (view/delete), backend ReceiptImageController tests
## [2026-09-15] update | T29 scan receipt — gate Scan tab on an active LLM profile (disabled + hint, manual fallback)
## [2026-09-16] ticket | T30 — Merge duplicate products implemented (dialog, POST /api/products/merge, transactional service)
## [2026-09-19] ticket | T10.1 Analytics overview API
## [2026-09-19] ticket | T10.2 Analytics page and drilldown category donut
## [2026-09-19] ticket | T10.3 Top 5 stores/lists bars
