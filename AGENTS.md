# Relationship-Assistant repository

This workspace is connected to https://github.com/ZeroKJ123/Relationship-Assistant.git.

## User-authorized Git workflow

- The user explicitly requests that completed fixes be committed and pushed to this GitHub repository immediately after appropriate verification. This authorization applies to future fixes in this project; do not ask for confirmation again for routine commits and pushes.
- Commit only files belonging to the requested task. Preserve unrelated user changes.
- Use the configured `origin` remote and the current task branch. Never force-push or overwrite remote changes.
- Keep credentials, tokens, `secrets.yaml`, private keys, runtime databases, logs, and backups out of Git. Inspect staged changes before committing.
- Report the commit and push result. If a push fails, preserve local work and explain the failure.

## Mandatory CodeStyle

- Read [CodeStyle.md](CodeStyle.md) before any application implementation, fix, or refactor; it is the mandatory project standard for human developers and AI agents.
- Every implementation task must follow its architecture, naming, typing, testing, performance, and diagnostics rules. The development plan must reference it and include the applicable quality checks.
- Do not silently bypass rules or weaken checks. Record conflicts and resolve them before the affected change; exceptions require an explicit reason and owner agreement.
- Preserve this section when installing or regenerating Laravel Boost guidance. Read CodeStyle again after generated instructions change.
- Planning and documentation changes do not authorize application implementation or deployment.

## Mandatory CodeStyle check before push

- Before every `git push` the AI agent must check all changes in the outgoing commits against [CodeStyle.md](CodeStyle.md), not only the latest change in the working directory.
- For code changes a self-review against the applicable CodeStyle items, successful Pint and PHPStan/Larastan runs and the applicable tests are required; for the interface a successful build is required as well. After fixes the checks are repeated for the final version of the code.
- A push is forbidden if CodeStyle violations were found or a required check failed, was not run or is unavailable. Do not get around the rule by disabling checks or by presenting a skipped check as passed; keep the work locally and report the reason.
- For documentation-only commits check the content, links and formatting of the changed documents, plus `git diff --check` for the outgoing range. Code checks are not required if the whole outgoing range contains no code changes.
- In the final message state the checks that were run and the result of the push. This rule is kept when instructions are updated and when Laravel Boost guidance is generated.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>

## Documentation

Every guide exists in English (`docs/en/`) and in Russian (`docs/ru/`) under the same file name. Keep both versions in sync when changing one.

| Document | Path | Description |
| --- | --- | --- |
| README | README.md | Purpose of the project and how to run it |
| Operations | docs/en/operations.md | Laptop, local network, processes, backups and updates |
| Development | docs/en/development.md | Checks and test isolation |
| Interface | docs/en/interface.md | Home screen, kitchen, duties, wishes, languages and accessibility |
| Accounts | docs/en/accounts.md | Registration, recovery and the interface language |
| Couples | docs/en/couples.md | Couples, invitations and the weekly limit |
| Requests | docs/en/requests.md | Requests, conditions and versions |
| Hearts and duties | docs/en/hearts-and-duties.md | Duty tabs, confirmation and the hearts ledger |
| Economy and catalogs | docs/en/economy-and-catalogs.md | Rules, the price reserve and catalogs |
| Products | docs/en/dishes-wishlist-money.md | Kitchen, wishes and money requests |
| Friends and dates | docs/en/friends-guests-dates.md | Personal friends, guests and dates |
| Mood and reminders | docs/en/mood-and-reminders.md | Mood, the schedule and reminders |
| Telegram and scheduling | docs/en/telegram-and-scheduling.md | Sign-in, linking, buttons, delivery and the schedule |

## Project entry points

- `app/Http/Controllers/DashboardController.php`, `app/Services/Couples/DashboardData.php`, `app/ViewModels/DashboardViewModel.php`: the home screen with the current account's data; `resources/views/home.blade.php`: the introduction for a guest.
- `resources/views/layouts/app.blade.php` and `resources/views/components/`: the interface layout.
- `tests/Support/TestEnvironment.php`: the mandatory protection of the test database.
- `.github/workflows/quality.yml`: checks with no deploy.

- `app/Actions/Auth/`, `app/Data/Auth/`, `routes/auth.php`: accounts and recovery.
- `app/Actions/Profiles/`, `app/Policies/UserPolicy.php`: profile ownership.
- `app/Enums/Locale.php`, `app/Services/Localization/LocalePreference.php`, `app/Http/Middleware/SetLocale.php`, `locale.update`: the interface language per user, the device cookie for guest pages and texts for another person in their language.
- `lang/en.json`: English texts keyed by the Russian phrases from the code; `lang/en/validation.php`, `lang/ru/validation.php`: input validation messages.

- `app/Actions/Couples/Agreements/`, `routes/agreements.php`: versions of the weekly limit and confirmations.
- `tests/Support/ConcurrentOperations.php`: the shared race protocol in an isolated MariaDB.

- `app/Actions/Requests/`, `routes/requests.php`: requests, answers and separate acceptance of a condition.
- `config/economy.php`: the initial rules; after the first save the versions from `EconomySettingsStore` apply.
- `app/Actions/Admin/`, `routes/admin.php`: the economy editor and the version audit.
- `app/Services/Hearts/ServicePayments.php`: reserving, deducting and releasing the agreed price.
- `app/Services/Catalogs/`, `database/seeders/CatalogSeeder.php`, `routes/catalogs.php`: shared catalogs, snapshots and personal methods.
- `app/Actions/Duties/`, `app/Services/Duties/`, `routes/duties.php`: separate completion and checking of obligations.
- `app/Services/Hearts/`, `app/Actions/Hearts/`: the ledger, reconciliation and manual operations by the current partner only.
- `app/Console/Commands/`, `routes/console.php`: the overdue check and balance reconciliation; the scheduler has to be started separately.

- `routes/products.php`, `app/Services/Requests/*Attachments.php`: snapshots of dishes and conditions, gift promises and payment details.
- `app/Services/Media/PrivatePhotos.php`: checking and private serving of catalog photos.

- `app/Actions/Friends/`, `routes/social.php`: personal friends and the archive.
- `app/Actions/Requests/CreateGuestVisit.php`, `app/Actions/Dates/`, `app/Services/Requests/SocialInvitations.php`: guest visits and dates with immutable snapshots.
- `docs/en/friends-guests-dates.md`: invitation rules, the migration and checks.

- `app/Actions/Mood/`, `app/Services/Mood/MoodHistory.php`: ratings, mood prompts and the personal schedule.
- `app/Actions/Reminders/`, `app/Services/Reminders/ReminderLifecycle.php`, `routes/care.php`: one-off reminders, cancellation and preparing delivery; the sender and the scheduler run as separate processes.

- `app/Services/Notifications/`, `app/Actions/Notifications/`, `app/Jobs/SendTelegramNotificationJob.php`: the transactional outbox, checks of the source/couple/link, the lease and Telegram results.
- `app/Services/Telegram/BotClient.php`, `config/services.php`: a transport that can be switched off and the secret of the protected webhook, with no secrets in logs.
- `app/Services/Scheduling/DueCare.php`, `care:process-due`, `telegram:dispatch-notifications`: the couple's local clock, a limited catch-up and queue recovery; the optional `compose.workers.yaml` and the separate processes are described in `docs/en/telegram-and-scheduling.md`.

- `app/Actions/Auth/Telegram/`, `app/Http/Controllers/Telegram/WebhookController.php`: one-time linking to the original browser and protected sign-in.
- `app/Services/Telegram/CallbackRouter.php`, `KeyboardFactory.php`, `WebhookHandler.php`: one press with permission/version checks and the shared Actions, an atomic receipt with no raw update.
- `app/Services/Telegram/ProfilePhoto.php`, `app/Http/Controllers/Auth/AvatarController.php`: importing a verified photo and serving it privately to its owner.
- `telegram:prune-controls`: the daily cleanup of technical confirmations after seven days.

- `public/manifest.webmanifest`, `public/sw.js`, `public/offline-v2.html`, `public/icons/`: PWA installation and a limited cache of public versioned files only; never cache personal responses and actions.
- `resources/js/modules/pwa.js`, `resources/views/components/pwa-help.blade.php`: manual installation, the network state, blocking offline forms and re-checking a signed-in page from the browser back cache.
- `tests/JavaScript/pwa.test.mjs`, `npm run test:pwa`: checks of the cache policy and client behaviour with no extra dependencies; part of Quality.

- `app/Actions/Auth/Telegram/IssueTelegramLinkCode.php`, `ConsumeTelegramLinkCode.php`, `profile.telegram.link`: one-time linking by the UUID from `/start`.
- `app/Services/Telegram/PollTelegramUpdates.php`, `telegram:poll-updates`, the `telegram-polling` Compose profile: receiving messages with no public webhook; never print the token.

- `app/Services/Realtime/ApplicationChanges.php`, `app/Events/ApplicationUpdated.php`, `routes/channels.php`, `resources/js/modules/realtime.js`: Reverb, a private signal with no personal data, refreshing pages while keeping drafts; the separate `realtime` service in Compose.
- `app/Services/Telegram/MoodStickers.php`, `app/Jobs/DownloadMoodStickerJob.php`, `app/Http/Controllers/MoodStickerController.php`: private WEBP/TGS/WEBM for the mood, downloaded outside transactions.
- `profile.timezone`, `app/Actions/Couples/UpdateTimezone.php`: the shared time zone, only in the detailed profile settings.

- `mood.day`, `app/Services/Mood/MoodDay.php`: the partner's permanent history by local day, with the average and details.
- `profiles.avatar`, `profile.compliments.store`: the photo for the current partner and encrypted personal compliments.
- `app/Actions/Movies/ManageMovieChoice.php`, `movies.selections.*`: shared sessions, two consents, the first match and a saved roulette.
- `app/Actions/Duties/CreateDutyReminders.php`, `ManageChoreDraw.php`: daily reminders and an agreed draw through the shared requests/obligations.
