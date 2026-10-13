# Running from a laptop

[Back to README](../../README.md) · [Checks](development.md) · [Telegram](telegram-and-scheduling.md)

The app can be used from a phone browser in the same trusted local network. The laptop must be on and Docker Desktop running. Laptop sleep stops access and schedule processing; after waking up, events that are still valid are processed, without a burst of stale questions.

## Address and start

In the local `.env` set `APP_BIND_ADDRESS=0.0.0.0`, a free `APP_PORT` and `APP_URL=http://LAPTOP-IP:PORT`. For regular use set `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=info`, `SESSION_DOMAIN=null` and, over HTTP, `SESSION_SECURE_COOKIE=false`. The IP is shown in the Wi-Fi settings; on macOS use `ipconfig getifaddr en0`. When the address changes, update `APP_URL` and restart the processes. Pinning the laptop's address in the router settings saves you from changing links.

The database port and the frontend dev port stay available only on the laptop. Do not forward the app port to the internet. HTTP sends sign-in and personal data unencrypted: this setup is meant for a trusted home network. Access from outside needs a separately configured HTTPS.

After the first install and the migrations from the README:

The commands below include the Telegram receiver for `TELEGRAM_MODE=polling`. If the bot is not used, drop `--profile telegram-polling` from the `up` commands; the queue and the schedule can keep running with the transport disabled.

```sh
npm run build
docker compose -f compose.yaml -f compose.workers.yaml --profile telegram-polling up -d
docker compose -f compose.yaml -f compose.workers.yaml ps
```

Open `APP_URL` from the phone, register your own account and save the recovery codes from the profile. The second member registers their account and accepts the invitation. Demo accounts are not needed for an install; use only `EconomySettingsSeeder` and `CatalogSeeder`, as in the README.

The schedule runs as a separate `scheduler` process and the queue as `telegram-worker`. Both restart together with Docker. To check the configured schedule and the hearts ledger:

```sh
docker compose exec -T laravel.test php artisan migrate:status --no-interaction
docker compose exec -T laravel.test php artisan schedule:list --no-interaction
docker compose exec -T laravel.test php artisan hearts:check-balances --no-interaction
```

Do not start a second scheduler. To stop while keeping the database:

```sh
docker compose -f compose.yaml -f compose.workers.yaml --profile telegram-polling stop
```

## Telegram and HTTPS

Over a local IP and HTTP the pages and regular forms of the app work. Installing the PWA with a service worker and the offline screen need trusted HTTPS; localhost on the laptop itself is a separate exception. A check in a desktop browser does not confirm installation in Safari on iOS or Chrome on Android.

For Telegram in a local network use `TELEGRAM_ENABLED=true`, `TELEGRAM_MODE=polling`, a valid token and the bot name. Receiving messages is enabled by the separate `telegram-poller` process; the start order is described in the [Telegram guide](telegram-and-scheduling.md). This mode needs no public HTTPS. Linking: `/start` in the bot → UUID → the code field in the profile. Notifications need a running queue, schedule and internet on the laptop. In `webhook` mode Telegram's servers must reach a public HTTPS address; do not enable webhook and polling at the same time.

## Updates and backups

Before an update save a consistent set: a MariaDB dump, `.env` with an unchanged `APP_KEY`, the whole `storage/app` directory and the code version. The key is needed to decrypt saved data; changing it is not a regular update. Backups contain personal data: keep them outside Git and move them to a separate protected medium. For a consistent copy stop the app, the queue and the schedule first; the database stays running.

```sh
docker compose -f compose.yaml -f compose.workers.yaml stop laravel.test telegram-worker scheduler telegram-poller
umask 077
mkdir -p work/backups
docker compose exec -T mariadb sh -c 'exec mariadb-dump --single-transaction --user="$MYSQL_USER" --password="$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > work/backups/homeassist.sql
cp .env work/backups/environment
tar -czf work/backups/private-media.tar.gz storage/app
git rev-parse HEAD > work/backups/code-version.txt
```

Check that every command succeeded and that the dump is not empty. These names overwrite the previous copy: move the finished set to a separate dated folder before the next backup. Check restoring on a separate throwaway database, not over your only working copy.

Update the code and the dependencies from the lock files, build the frontend, run `migrate --force` in a confirmed environment and start the processes:

```sh
composer install --no-interaction
npm ci
npm run build
docker compose run --rm --no-deps laravel.test php artisan migrate --force --no-interaction
docker compose -f compose.yaml -f compose.workers.yaml --profile telegram-polling up -d
```

Do not use `migrate:fresh`, `db:wipe` or `down -v` in a production environment. Rolling back the code version may need the earlier schema and the saved set: restoring is checked separately first, then done with the workers stopped. New migrations do not guarantee restoring lost data on rollback.

### Checking a restore

Prepare a separate copy of the project at the code version from `code-version.txt`. Copy the saved `environment` to its `.env`, keep the earlier `APP_KEY`, but set a new `COMPOSE_PROJECT_NAME`, free ports and `APP_BIND_ADDRESS=127.0.0.1`. Leave the bot disabled. The new project name must create a separate empty volume; before importing, check the container name and its volume. Do not point the import at an existing production database.

Start only this copy's MariaDB and wait for `healthy`. Import the saved dump into its empty database, unpack the private files from the saved archive into the root of the project copy, then start only the app:

```sh
docker compose up -d mariadb
docker compose ps
docker compose exec -T mariadb sh -c 'exec mariadb --user="$MYSQL_USER" --password="$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < work/backups/homeassist.sql
tar -xzf work/backups/private-media.tar.gz
docker compose up -d laravel.test
docker compose exec -T laravel.test php artisan migrate:status --no-interaction
docker compose exec -T laravel.test php artisan hearts:check-balances --no-interaction
```

Check a saved user's sign-in, the history and serving of their photos. Compare record counts and rule versions with the original copy. Turn the queue and the schedule on only after you decide to use the restored environment: a verification copy must not send messages or change deadlines of the real couple. Restoring an initial empty install was checked in a throwaway MariaDB; this does not replace a periodic check of a copy with your future data.

## Diagnostics

If the app is unreachable, check the laptop's power/sleep, Docker, that the devices are on the same network, the current IP, the port and that the system allows incoming connections. Then check `docker compose ... --profile telegram-polling ps`, where `...` stands for both files above. If pages work but deadlines are not processed, check `scheduler` and `schedule:list`. For delivery problems follow the [Telegram guide](telegram-and-scheduling.md).

`DEBUG` is meant for local development; `INFO` for processing stages, `WARN` for refusals and retries, `ERROR` for unexpected errors. The working log is `storage/logs/laravel.log`. When investigating, use the `schedule.*`, `notification.*`, `telegram.transport_failed` codes and `failed_jobs`; do not publish `.env`, recovery codes, a URL with a token, payment details, message contents or whole personal pages. Hearts reconciliation reports discrepancies and never fixes them silently.

## Release 0.18.0
Before the new `add_partner_features` migration, save the data set as described above and stop the app, the queue, the schedule and the bot receiver. The migration adds date/emoji/rating fields and tables for movies, votes and cars; it does not rewrite earlier data. After it, start the processes with the new code and rebuilt assets. Rolling the migration back drops the new tables and fields (with their data) but leaves the created fill-up methods in the catalog and earlier condition snapshots; a regular update needs no rollback. To return to an old version use a verified backup and the matching code version.

## Live updates (0.21)

Laravel Reverb runs as the separate `realtime` service from `compose.workers.yaml`. The app's HTTP stays on the same port; WebSocket uses `REVERB_PUBLIC_PORT` (8089 by default). The phone connects to the same laptop name/IP automatically. Do not use `localhost` in the phone's address.

In the private `.env` enable `REALTIME_ENABLED=true` and `BROADCAST_CONNECTION=reverb`, and set independent random `REVERB_APP_ID`, `REVERB_APP_KEY` and `REVERB_APP_SECRET`. For Compose: `REVERB_HOST=realtime`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`, `REVERB_PUBLIC_PORT=8089`. Only the Reverb app key is published; the secret stays on the server. `.env.example` keeps the transport off.

For external HTTPS (for example a Cloudflare Tunnel) the proxy terminates TLS and passes requests to the app over HTTP. In `.env` set `APP_URL=https://DOMAIN` and `TRUSTED_PROXIES` — the address the proxy comes from into the container (the Docker network gateway), otherwise the app builds `http://` links. On an HTTPS page WebSocket connects to the same domain on `REVERB_SECURE_PUBLIC_PORT` (443 by default), so the proxy must route the `/app/*` path to the `realtime` service (`http://localhost:8089`) and everything else to the app. The HTTP → HTTPS redirect is enabled on the proxy side. `SESSION_SECURE_COOKIE=true` is acceptable only if signing in over HTTP from the local network is no longer needed.

```sh
docker compose -f compose.yaml -f compose.workers.yaml --profile telegram-polling up -d realtime
docker compose -f compose.yaml -f compose.workers.yaml restart laravel.test telegram-worker scheduler telegram-poller
```

The `default` queue, which `telegram-worker` already listens to, delivers refresh signals and downloads stickers. When PHP or dependencies are updated, restart `realtime` too. Sending a signal starts after commit; a rollback sends nothing. All signed-in pages subscribe to a private shared channel **with no content and no identifiers**: when data changes, they fetch current HTML through their own session and the usual access policies. Technical records of sessions, queues and Telegram receipts create no new signals. An open dialog postpones the refresh, and entered data and open settings are kept. After the connection is restored the page is reconciled; this also recovers changes missed while the server was unreachable.

For release 0.21, before the `add_sticker_to_mood_entries_table` migration save the production database, `.env` and the whole `storage/app/private`, and stop the sender/receiver/schedule. The migration adds nullable fields and keeps old moods; after applying it, start the processes and check channel authorization, the mood in two accounts and a new sticker. Rolling the columns back removes the sticker links; restoring media and links needs a backup.

## Update 0.22
Before add_care_and_selection_features save the database/.env/private media set and stop the app/queue/scheduler/poller/Reverb. The migration adds nullable users.compliments, movies.link/photo_path and three new tables movie_choices, duty_reminder_batches and chore_draws with indexes/foreign keys; it does not rewrite earlier records. After the migration and the build restart all processes, including Reverb. The scheduler/queue stay the same and there are no new .env keys. Down drops the new tables/fields: lost results/compliments/links need a backup, and JPEGs stay in private storage.

## Private admin panel (0.23)
The address is `/admin-panel`, entered after signing in to a regular account. `.env` holds `ADMIN_PANEL_LOGIN` and `ADMIN_PANEL_PASSWORD_HASH` (bcrypt, not the plain password). The login must exactly match the owner's login. An empty setting disables the panel. Do not commit the secret password/hash; `.env.example` has only empty keys. First apply the new migrations in a confirmed environment with the standard update procedure; then reload the config and the processes. Checks and development never apply migrations to production data on their own.

The second password opens the panel for 30 minutes; signing in, signing out and changing the hash end the previous access session. Another account gets no access even with the password or is_admin. Failed sign-ins are rate-limited. The statistics include users, active couples, requests, duty completion, ratings and notification delivery. Users and the log are paginated.

"Feature testing" opens the regular interface as the selected user within the owner's current session. A separate banner and a return button stay visible. All domain functions use the usual checks, confirmations and Actions. You can pick the first partner, create a request/pick, come back and pick the second. A simultaneous wheel needs two independent browser sessions. These are real actions with real notifications/hearts: use a test couple for experiments. Signing out and linking Telegram are forbidden in this mode. A system test request is addressed to a member of an active couple; their partner checks the completion. The system-origin marker does not replace the reward and penalty rules.

`admin_audits` stores the owner's/selected user's ID, the operation name, the HTTP result and the time, with no personal text or passwords. A failed operation may be left without an HTTP result. The history is not editable in the panel. The new migrations add bio, spin_started_at, is_self_requested, created_by_admin_id and the log; reversing them deletes this new data, so a backup is needed before a rollback.

## Themes and the builder (0.24)

After a consistent backup, the new migration adds `appearance_settings` with the shared theme library, icon replacements and the home screen layout. Make sure `storage/app/appearance` persists between releases and is writable by the app process. This directory holds uploaded backgrounds and icons; backing up `storage/app` includes it along with private files. The secret `.env` and dumps stay outside Git.

After the migration and the build restart the app and the existing background processes. Open `/admin-panel`, unlock the panel with your second password and check the "Themes", "Icons", "Home screen builder" and "Balance" sections. The settings are saved for all users. The new features need no new environment keys or dependencies.

## Interface language (0.32)

No migrations or new dependencies are needed. After updating the code, rebuild the assets and restart the app, the queue and the bot receiver: notifications are written in the queue worker in the recipient's language. `APP_LOCALE` sets the language for guests and new accounts; it is `en` in `.env.example`. A value from an existing `.env` is kept: with `APP_LOCALE=ru` the sign-in pages stay Russian. Existing accounts keep Russian until the person picks another language in the profile. `APP_FALLBACK_LOCALE` is no longer read: the fallback language is fixed to English. More in [accounts](accounts.md#interface-language-032).
