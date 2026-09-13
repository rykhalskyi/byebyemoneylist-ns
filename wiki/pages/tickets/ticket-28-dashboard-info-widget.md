---
created: 2026-09-13
type: ticket
status: in-progress
summary: T9.5 — Info widget (app logo + version injected via the page template)
---

# T9.5 — Info widget + app version

Part of [epic](../epics/nextcloud-web-app.md) · [spec](../specs/dashboard.md) · [plan](../plans/dashboard.md).

## Spec

An **Info** widget shows the app logo (`img/app.svg`) and the app version. The
version comes from `appinfo/info.xml` (single source of truth) via
`IAppManager::getAppVersion()`, injected into the page template rather than a new
endpoint ([D-09](../../decisions.md)). Depends on T9.1.

## Plan

1. `lib/Controller/PageController.php` — inject `IAppManager`, pass
   `['version' => $appManager->getAppVersion(Application::APP_ID)]` to the template.
2. `templates/index.php` — add `data-version="<?php p($_['version']); ?>"` to
   `#byebyemoneylist`.
3. `src/main.ts` — read the data attribute and `app.provide('appVersion', ...)`.
4. `src/components/dashboard/widgets/InfoWidget.vue` — logo via `imagePath` from
   `@nextcloud/router` + injected version.
5. Wire into `Dashboard.vue`; `l10n/{en,de,uk}.json` + `npm run l10n`.
6. Test rendering with a provided version.

## Outcome

Pending implementation.
