[Back to README](../../README.md) · [Interface →](interface.md)

# Development and checks

## Checking changes

PHP 8.4 and the dependencies from `composer.lock` are required. Node.js 22+ is used for the build.

```sh
composer install
npm ci
composer check
npm run build
```

`composer check` runs Pint in test mode, PHPStan/Larastan at level 8 and Pest. The rules are in `pint.json`, `phpstan.neon` and [CodeStyle](../../CodeStyle.md). Regular PHP files require strict_types; Blade and the auto-generated cache are excluded. Pint uses `no_useless_else`; the preference for early returns is also checked in self-review. To fix formatting: `vendor/bin/pint`.

GitHub Actions runs the same checks and the build, with no deploy. Before a push the whole outgoing range is checked; a failure of any required check forbids pushing.

## Isolated testing

`phpunit.xml` forces testing, SQLite `:memory:`, a test encryption key, in-memory sessions/cache and a synchronous queue. That key serves tests only. A config cache, a persistent database or a connection URL cause a refusal before any data is prepared. Only the SQLite connection remains in the test application; unexpected HTTP requests are blocked.

```sh
php artisan test --compact
php artisan test --compact --filter=TestEnvironment
```

Scenarios use Pest `it()`. Isolation tests check the refusal for a persistent database, a non-test environment and a connection URL. Interface scenarios check the Russian texts, escaping of personal data and accessible fields. A production MariaDB is not used. Couples, invitations and agreements have a separate MariaDB environment: SQLite does not prove that locks and races are handled correctly.

## Concurrent operations in MariaDB

Use a separate throwaway container, with no production volumes:

```sh
docker run --rm -d --name homeassist-couples-test --tmpfs /var/lib/mysql:rw,size=512m -p 127.0.0.1:33071:3306 -e MARIADB_DATABASE=homeassist_couples_testing -e MARIADB_USER=homeassist_test -e MARIADB_PASSWORD=test_only_password -e MARIADB_RANDOM_ROOT_PASSWORD=yes mariadb:11.4
docker exec homeassist-couples-test healthcheck.sh --connect --innodb_initialized
vendor/bin/pest --configuration=phpunit.mariadb.xml
docker stop homeassist-couples-test
```

The key and the password here are meant only for the throwaway test database. The environment check before migrations requires testing, no config cache and no DB_URL, host 127.0.0.1, port 33071, the name homeassist_couples_testing and the user homeassist_test. Other connections are removed from the test application. These tests recreate only the dedicated test schema; do not attach production data to this container. The shared ConcurrentOperations protocol is used by invitations and agreements. PHP processes are synchronized by a start barrier; Pao output adaptation is disabled only in the child protocol, and the checks stay on.

## Duties and the hearts ledger

`DutiesTest` and `HeartsTest` cover separate obligations, both confirmations, repeated operations, roles, rate snapshots, a negative balance, actual amounts and limits. `DutyConcurrencyTest` and `HeartConcurrencyTest` start independent MariaDB processes for races of marks/checks, balances, the starting bonus, rewards, thanks and the weekly quota. Test factories of the new models keep relations and the balance consistent. These checks do not run production migrations.

The `duties:check-overdue` and `hearts:check-balances` commands are described together with the schedule and limits in [duties and hearts](hearts-and-duties.md). Overdue handling needs a running scheduler in an environment with migrations applied; ledger reconciliation reports an error and never fixes it silently.

## Settings, reserve and catalogs

`EconomySettingsTest`, `ServicePaymentsTest` and `CatalogsTest` cover administrator permissions, versions and conflicts, price agreement, the protected reserve, deduction only after the check, mutual cancellation, catalog snapshots, privacy and repeated submission. `EconomyPaymentConcurrencyTest` covers independent processes: competing settings versions, two orders against one balance, a penalty at the same time as a deduction, and cancellation at the same time as a check. All checks use the same protected SQLite/MariaDB environments.

```sh
php artisan test --compact tests/Feature/EconomySettingsTest.php tests/Feature/ServicePaymentsTest.php tests/Feature/CatalogsTest.php
vendor/bin/pest --configuration=phpunit.mariadb.xml tests/Integration/EconomyPaymentConcurrencyTest.php
```

Initial seeding, implementation boundaries and the reverse order of migrations are described in [economy and catalogs](economy-and-catalogs.md).

## Laravel Boost

Boost is installed as a dev dependency; the guidelines are refreshed with `APP_ENV=local php artisan boost:install --guidelines --no-interaction`. After an update re-read AGENTS.md and CodeStyle.md. CodeStyle takes priority over Boost's general guidance, including the mandatory Pint --test. The custom Git rules and pre-push checks are kept.

Money calculations use `brick/math`: amounts are stored exactly, and the app makes no bank transfers. Logs are controlled by `LOG_LEVEL` and hold the technical stage and the refusal reason, without personal text or secrets.

## PWA checks and hosting

`npm run test:pwa` runs Node.js tests with no third-party libraries: protection of personal data, no offline actions, caching of public assets, cache limits/refresh, handling a storage failure, the manual install prompt and returning to a signed-in page. The command is part of GitHub Quality after the build. `PwaScreensTest` checks private HTML headers, the manifest and icon sizes. In development through Vite the worker is not registered; check the built app.

Hosting needs HTTPS and the site at the domain root; local localhost is fine only for development. `/sw.js` must have a JavaScript MIME type and `/manifest.webmanifest` must be `application/manifest+json`; serve both with `Cache-Control: no-cache`. The public Apache config adds the header when mod_headers is present and the MIME type when mod_mime is. For Nginx or another target these rules have to be configured separately once the environment is confirmed; a proxy/CDN must not cache personal HTML/JSON. Never mark the worker file immutable. Assets in `/build/assets/` have a hash in their names; a new build creates new keys. When the worker policy changes, change CACHE_NAME; when the offline screen or icons change, change their versioned paths. The PWA needs no migrations or new dependencies.

Before a release: open the public HTTPS site in a real Chrome on Android and Safari on iOS, install from the profile/menu, check the standalone launch and safe-area, turn the network off, make sure the offline screen has no personal data, restore the network, then sign out and check going back. Check the update after a new build without losing unfinished input. The currently agreed way of use is a browser over HTTP in the local network. Accepting PWA installation on real devices remains a separate check for a future HTTPS hosting; local tests do not replace it.

## Process check

`RuntimeProcessesTest` takes a reminder through the real database-queue worker with a fake Telegram API: scanning again does not send a second message; disabling the bot keeps it pending, and after enabling the dispatcher restores delivery. The database is in-memory SQLite and external HTTP requests are forbidden. Both PHPUnit configs and the environment of child MariaDB processes force Telegram off and clear the real bot's token, name and secret. Only the Telegram scenarios turn the transport on, with a fake token and `Http::fake`.

## See also

- [Interface](interface.md)
- [CodeStyle](../../CodeStyle.md)
- [Running the project](../../README.md)

The new block is checked in `DishesTest`, `WishlistTest`, `MoneyRequestsTest` and `ProductConcurrencyTest`. Photo processing needs PHP GD (local PHP and CI are set up). Amounts and payment details are checked with no real bank calls; test photos are stored on a faked private disk.

Checks for 0.21: `MoodStickerTest`, `RealtimeTest`, `InterfaceSettingsTest`, updated `TelegramCallbacksTest` and `TelegramConcurrencyTest`; the client checks in `live-content.test.mjs` are part of `npm run test:pwa`. The transport is forced off in both test configs and in the environment of child MariaDB processes. Sticker checks use a fake Telegram and a private test disk; HTTP is checked outside transactions. For a manual check open two test accounts: a mood/request change shows in the other one without a reload, a draft is kept, and restoring the connection pulls in missed changes.

Checks for 0.22: CareSelectionTest covers local-day boundaries, photo privacy, a single mood outbox, compliments, daily slots/duty freshness, a mutual start, the first match, the shared wheel result and creating an obligation only after two consents. SelectionConcurrencyTest covers parallel consents/likes/spins and a single result/single obligation in an isolated MariaDB. care.test.mjs covers holding/cancelling/scrolling/keyboard use of a card.

Checks for 0.23: `ProfileAndRequestProgressTest`, `SharedSpinTest`, `SelfMoodPromptTest`, `AdminPanelTest`; `SelectionConcurrencyTest` was updated for races after two consents and for presence. In the test's child processes the presence of two pages is set explicitly, because the array cache store is isolated per process. Client tests cover computing the shared time, holding a card, uploading an avatar and cancelling/repeating the presence check. The appearance check uses only HTML from isolated test scenarios, with no access to a production database.

Checks for 0.24: `AdminAppearanceTest` covers access, creating/applying a theme, calendar boundaries of seasons, pictures, icon priority and restoring, the layout, conditions, escaping and rejecting stale editors. `AdminBalanceTest` covers exact amounts, repeated UUIDs, the protected reserve, a negative balance, limits and access expiry. `AdminCustomizationConcurrencyTest` covers simultaneous repeated operations, independent credits/deductions, the reserve and competing revisions in a separate MariaDB. The client scenarios in `appearance.test.mjs` are part of `npm run test:pwa`.

Manual check: sign in to `/admin-panel`, create a theme and apply it, enable the seasonal mode, replace and restore an icon. In the builder move a block with the handle and the arrows, turn a card off and back on, choose the "More than zero cars" condition, add text, check the wide/narrow layout, save and open the home screen. In a test account's balance add/take away an amount and check the log. Open one revision in two tabs: the second saved version must be rejected. Browser previews were taken from isolated test pages; production data was not used.

Checks for 0.32: both PHPUnit configs force `APP_LOCALE=ru`, so scenarios keep comparing the Russian texts — they also serve as translation keys. `LocaleTest` covers English by default, choosing the language in the profile and on guest pages, the user's language taking priority over the cookie, texts for another person in their language, and every interface string having an English text with the same placeholders.
