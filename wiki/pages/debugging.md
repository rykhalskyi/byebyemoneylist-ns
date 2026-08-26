---
created: 2026-08-26
type: build-deploy
tags: [debug, nextcloud, log, console]
related:
  - "build-deploy.md"
  - "project-overview.md"
---

# Debugging

How to debug the app running on the dev instance (`nextcloud.local`, Docker: `master-nextcloud-1`).

## Backend (PHP/API) errors

Main log — Nextcloud writes structured JSON lines here:

```bash
docker exec master-nextcloud-1 sh -c 'tail -f /var/www/html/data/nextcloud.log'
```

Filter usefully (this instance has `debug => true`, `loglevel => 2`):

```bash
# our app only, newest first
docker exec master-nextcloud-1 sh -c 'grep '"'"'"app":"byebyemoneylist"'"'" /var/www/html/data/nextcloud.log | tail -50'
```

Each line has `level` (4=error, 3=warning, 2=info) and, for exceptions, a full `exception.Trace` with `file:line`. PHP fatal errors also land here.

From the browser, the same log is visible in Nextcloud → **Settings → Administration → Logging**; a failing request shows the JSON error in the **Network tab → Response** of the failing XHR.

Run a controller in isolation / see a live request (a 500 logs a stack trace to `nextcloud.log`):

```bash
curl -s -u alice:alice -H "OCS-APIRequest: true" -H "Accept: application/json" \
  "http://nextcloud.local/ocs/v2.php/apps/byebyemoneylist/api/lists"
```

DB query profiling — the dev image ships the **Profiler** app; after any request `occ` prints a link like `http://nextcloud.local/index.php/apps/profiler/profiler/db/<id>` showing the SQL that ran.

Static analysis / tests (catch most problems before runtime):

```bash
docker exec -w /var/www/html/apps-extra/byebyemoneylist master-nextcloud-1 composer psalm
docker exec -w /var/www/html/apps-extra/byebyemoneylist master-nextcloud-1 composer test:unit
docker exec -w /var/www/html/apps-extra/byebyemoneylist master-nextcloud-1 composer cs:check
```

## Frontend (Vue/console)

Browser DevTools (F12) shows most frontend problems:

- **Console tab** — Vue warnings/errors, unhandled promise rejections (e.g. failed API calls), and anything `console.error()`-ed.
- **Network tab** — each `/ocs/v2.php/apps/byebyemoneylist/api/...` call: status code, request/response JSON. A 4xx/5xx points to the backend; a CORS/401 points to auth.
- **Application tab → Local Storage / Cookies** — verify you're logged in (the `oc_` session cookie).

Live rebuild — the app serves built assets from `js/` (gitignored). For dev, run the watcher so `src/*.vue` changes rebuild instantly:

```bash
npm run watch
```

Then reload `http://nextcloud.local/index.php/apps/byebyemoneylist` — `debug => true` means no asset cache. If the page shows a blank `#byebyemoneylist` div, open the Console: a JS build/runtime error is usually there.

The Shopping Lists screen only renders when the "Shopping Lists" nav item is active; the other four tabs still show a plain `<h2>`.

## Known harmless noise

- `Refused to apply style from .../profiler/css/profiler-toolbar.css ... MIME type ('text/html')` — the dev **Profiler** app injects an unbuilt toolbar for admins; its assets 404. Benign. (Fix if desired: build the toolbar in `apps-extra/profiler`, or disable via `occ config:system:delete profiler`.)

## Where errors surface per layer

- Bad PHP syntax / fatal → `nextcloud.log` (`level: 4`), page returns 500.
- Route not found (wrong `ApiRoute` URL) → OCS `998 Invalid query` in the Network response.
- Vue template/render error → Browser Console.
- API returns empty data but no error → check the Network response shape (`ocs.data.lists`) and the DB: `occ migrations:status byebyemoneylist` + the MariaDB tables (`oc_bbml_*`).
- Wrong URL prefix (`generateUrl` vs `generateOcsUrl`) → `404` on `/index.php/apps/...` paths; OCS endpoints live under `/ocs/v2.php/apps/...` and must be built with `generateOcsUrl()`.

## DB connect

```bash
docker exec -it master-database-mysql-1 mariadb -u root -p
```
password: nextcloud

```bash
USE nextcloud;
SHOW TABLES;
```

tables:
- oc_bbml_lists
- oc_bbml_categories
- oc_bbml_stores