# Bye Bye Money List

Nextcloud application for shopping list and expenses management.

## Development setup (nextcloud-docker-dev)

This project is developed against the [nextcloud-docker-dev](https://github.com/nextcloud/nextcloud-docker-dev)
environment. The following steps assume you have a running instance in `/home/admin/Source/nextcloud-docker-dev`.

### 1. Bind the project folder to the container

The `docker-compose.override.yml` in the nextcloud-docker-dev directory is merged automatically with
`docker-compose.yml`. Create it (or extend it) to bind the project folder into the container's `apps-extra` directory,
so the app lives at `/var/www/html/apps-extra/byebyemoneylist`:

```yaml
# /home/admin/Source/nextcloud-docker-dev/docker-compose.override.yml
services:
  nextcloud:
    volumes:
      - [your-path-to]/byebyemoneylist-ns:/var/www/html/apps-extra/byebyemoneylist
```

Afterwards apply the change and start the container:

```bash
cd /home/admin/Source/nextcloud-docker-dev
docker compose up -d nextcloud
```

### 2. Add and enable the app in Nextcloud

The app is then picked up automatically from the `apps-extra` directory. To enable it either use `occ`:

```bash
cd [your-path-to]/nextcloud-docker-dev
./scripts/occ.sh app:enable byebyemoneylist
# or directly:
docker compose exec --user www-data nextcloud ./occ app:enable byebyemoneylist
```

or enable it in the web interface (Apps → Disabled apps → Bye Bye Money List → Enable) at
`https://nextcloud.local` (user `admin`, password `admin`).

### 3. Build the frontend after changes

The frontend is compiled with Vite into the `js/` directory. After modifying `src/`, rebuild the assets:

```bash
cd [your-path-to]/byebyemoneylist-ns
npm install        # only needed once / after dependency changes
npm run build
```

Because the project folder is bind-mounted, the freshly built assets are instantly available inside the container.
For development use `npm run watch` to rebuild automatically on every change, then reload the app page (disable
the HTTP cache in your browser or press `Ctrl+Shift+R`).

PHP changes under `lib/`, `templates/` and `appinfo/` do not need a build step - just reload the page.

## Resources

### Documentation for developers:

- General documentation and tutorials: https://nextcloud.com/developer
- Technical documentation: https://docs.nextcloud.com/server/latest/developer_manual

### Help for developers:

- Official community chat: https://cloud.nextcloud.com/call/xs25tz5y
- Official community forum: https://help.nextcloud.com/c/dev/11
