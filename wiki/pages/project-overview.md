---
created: 2026-08-26
type: overview
tags: [nextcloud, php, vue, overview]
related:
  - "build-deploy.md"
  - "epics/nextcloud-web-app.md"
---

# Project Overview

**Bye-Bye Money List** Nextcloud app (`byebyemoneylist`) — a shopping list manager and expense tracker. Provides the **server side** (multi-user storage, sync, sharing, permissions) for the Bye-Bye Money List Android app, plus an optional **web UI**.

## Tech stack

- **Backend**: PHP 8.1, Nextcloud AppFramework (Nextcloud 31–35). No database schema yet.
- **Frontend**: Vue 3 (Composition API, `<script setup>`), TypeScript, Vite build, `@nextcloud/vue` components, `@mdi/js` icons.
- **Testing**: PHPUnit (`tests/`), ESLint + Stylelint for the frontend, psalm + php-cs-fixer for the backend.
- **CI**: GitHub Actions workflows (lint, psalm, node build, openapi spec).

## Folder structure

```
appinfo/info.xml            # app metadata, navigation entry
lib/
  AppInfo/Application.php   # app bootstrap (registration, boot)
  Controller/               # PageController (frontend page), ApiController (OCS API)
src/
  main.ts                   # Vue entry point
  App.vue                   # root component, view switching, navigation
  components/Menu.vue       # left navigation menu
templates/index.php         # server-side page template
css/, img/, js/             # built assets
tests/                      # PHPUnit tests
```

## Architecture

- **Navigation**: single-page Vue app with 5 views — Dashboard, Shopping Lists, Analytics, Catalog, Settings. View switching is handled in `App.vue`; `Menu.vue` renders the `NcAppNavigation` sidebar.
- **Backend**: standard Nextcloud AppFramework controllers. `PageController::index()` renders the Vue page; `ApiController` is a placeholder OCS endpoint (`/api`) returning `Hello world!`.
- **Data layer**: none yet. No entities, mappers, or database migrations exist — to be built alongside the domain model (lists, items, products, categories, stores, prices).

## Key concepts (domain model to mirror)

- **Shopping lists** — name, create/purchase dates, store, `finalTotal`, categories (cross-ref), status (new/finished/archived), recurrence, subscription/income flags, share state, `syncId`.
- **List items** — reference a product, quantity, checked state, per-item price, discount, custom name; `productId == 0` for coupons/unknown products.
- **Products** — name, barcode, picture, one category, status (added/reviewed/barcode), aliases, analog links, price history, flags.
- **Categories** — hierarchical (`parentId`), color, emoji, income flag.
- **Stores** — name, address, logo, receipt name, associated categories.
- **Prices** — product price per store over time (price history).
- **Dual price system** — estimated (item sum) vs actual (`finalTotal`); settings rule decides which is used in analytics.
- **Sync** — per-list `*.bbl.json` shared via WebDAV, replaced by a real server API; conflict resolution via `lastModifiedAt` / `lastSyncTimestamp`.
