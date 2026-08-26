---
created: 2026-08-26
type: build-deploy
tags: [build, test, deploy, nextcloud]
related:
  - "project-overview.md"
  - "debugging.md"
---

# Build, Test & Deploy

## Prerequisites

- Node.js ^24, npm ^11
- PHP ^8.1, Composer
- A Nextcloud 31+ server instance for manual testing

## Setup

```bash
npm install
composer install
```

## Build

```bash
npm run build          # production build via Vite (output to dist/, copied to js/)
npm run dev            # development build
npm run watch          # watch mode for development
```

The built bundle is served from `js/` via the `templates/index.php` page.

## Lint & typecheck

```bash
composer lint          # php -l syntax check on lib/
composer cs:check      # php-cs-fixer dry-run
composer psalm         # static analysis
npm run lint           # eslint src
npm run stylelint      # stylelint src/**/*.vue *.scss *.css
```

## Tests

```bash
composer test:unit     # PHPUnit (tests/phpunit.xml)
```

Frontend has no test framework configured yet.

## CI

GitHub Actions workflows in `.github/workflows/`:
- `lint-*.yml` — PHP lint, php-cs, eslint, stylelint, info.xml validation
- `psalm-matrix.yml` — psalm across Nextcloud versions
- `node.yml` — Node build
- `openapi.yml` — OpenAPI spec generation
- `block-unconventional-commits.yml`, `fixup.yml`, `npm-audit-fix.yml` — hygiene checks

## Deploy

- For development: symlink or copy the app directory into your Nextcloud `apps/` directory and enable it (`occ app:enable byebyemoneylist`).
- For release: build assets, then ship to the Nextcloud App Store (see README instructions).

## Updates

- [2026-08-26]: Initial page from scaffold scan. No deployment pipeline beyond local dev yet.
- [2026-08-26]: Linked the new [debugging](debugging.md) page (logs, browser console, profiler, common failures).
