# Home Assistant repository

This workspace is connected to https://github.com/ZeroKJ123/Relationship-Assistant.git.

## User-authorized Git workflow

- The user explicitly requests that completed fixes be committed and pushed to this GitHub repository immediately after appropriate verification. This authorization applies to future fixes in this project; do not ask for confirmation again for routine commits and pushes.
- Commit only files belonging to the requested task. Preserve unrelated user changes.
- Use the configured `origin` remote and the current task branch. Never force-push or overwrite remote changes.
- Keep credentials, tokens, `secrets.yaml`, private keys, runtime databases, logs, and backups out of Git. Inspect staged changes before committing.
- Report the commit and push result. If a push fails, preserve local work and explain the failure.

## Home Assistant configuration

The location and connection details of the running Home Assistant instance have not yet been supplied. Do not assume that this workspace is its live configuration directory. Obtain those details before changing or deploying live configuration.

## Mandatory CodeStyle

- Read [CodeStyle.md](CodeStyle.md) before any application implementation, fix, or refactor; it is the mandatory project standard for human developers and AI agents.
- Every implementation task must follow its architecture, naming, typing, testing, performance, and diagnostics rules. The development plan must reference it and include the applicable quality checks.
- Do not silently bypass rules or weaken checks. Record conflicts and resolve them before the affected change; exceptions require an explicit reason and owner agreement.
- Preserve this section when installing or regenerating Laravel Boost guidance. Read CodeStyle again after generated instructions change.
- Planning and documentation changes do not authorize application implementation or deployment.

## Обязательная проверка CodeStyle перед push

- Перед каждым `git push` AI-агент обязан проверить все изменения в отправляемых коммитах по [CodeStyle.md](CodeStyle.md), а не только последнее изменение в рабочей папке.
- Для изменений кода обязательны саморевью по применимым пунктам CodeStyle, успешные Pint и PHPStan/Larastan, а также применимые тесты; для интерфейса дополнительно нужна успешная сборка. После исправлений проверки повторяются для окончательной версии кода.
- Push запрещён, если найдены нарушения CodeStyle, обязательная проверка завершилась ошибкой, не запускалась или недоступна. Нельзя обходить правило отключением проверок или выдавать пропущенную проверку за успешную; нужно сохранить локальную работу и сообщить причину.
- Для коммитов только с документацией проверяются содержание, ссылки и форматирование изменённых документов, а также `git diff --check` для отправляемого диапазона. Проверки кода не требуются, если весь отправляемый диапазон не содержит изменений кода.
- В итоговом сообщении указать выполненные проверки и результат push. Это правило сохраняется при обновлении инструкций и генерации Laravel Boost.

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

| Document | Path | Description |
| --- | --- | --- |
| README | README.md | Назначение и запуск проекта |
| Operations | docs/operations.md | Ноутбук, локальная сеть, процессы, резервирование и обновления |
| Development | docs/development.md | Проверки и изоляция тестов |
| Interface | docs/interface.md | Главная, кухня, дела, желания и доступность |
| Accounts | docs/accounts.md | Регистрация и восстановление |
| Couples | docs/couples.md | Пары, приглашения и недельный лимит |
| Requests | docs/requests.md | Просьбы, условия и версии |
| Hearts and duties | docs/hearts-and-duties.md | Вкладки дел, подтверждение и журнал сердечек |
| Economy and catalogs | docs/economy-and-catalogs.md | Правила, резерв цены и каталоги |
| Products | docs/dishes-wishlist-money.md | Кухня, желания и денежные просьбы |
| Friends and dates | docs/friends-guests-dates.md | Личные друзья, гости и свидания |
| Mood and reminders | docs/mood-and-reminders.md | Настроение, расписание и напоминания |
| Telegram and scheduling | docs/telegram-and-scheduling.md | Вход, привязка, кнопки, доставка и расписание |

## Project entry points

- `app/Http/Controllers/DashboardController.php`, `app/Services/Couples/DashboardData.php`, `app/ViewModels/DashboardViewModel.php`: главная с данными текущего аккаунта; `resources/views/home.blade.php`: знакомство для гостя.
- `resources/views/layouts/app.blade.php` и `resources/views/components/`: каркас интерфейса.
- `tests/Support/TestEnvironment.php`: обязательная защита тестовой базы.
- `.github/workflows/quality.yml`: проверки без деплоя.

- `app/Actions/Auth/`, `app/Data/Auth/`, `routes/auth.php`: аккаунты и восстановление.
- `app/Actions/Profiles/`, `app/Policies/UserPolicy.php`: собственность профиля.
- `lang/ru/validation.php`: русские сообщения проверки ввода.

- `app/Actions/Couples/Agreements/`, `routes/agreements.php`: версии недельного лимита и подтверждения.
- `tests/Support/ConcurrentOperations.php`: общий протокол гонок в изолированной MariaDB.

- `app/Actions/Requests/`, `routes/requests.php`: просьбы, ответы и отдельное принятие условия.
- `config/economy.php`: начальные правила; после первого сохранения действуют версии из `EconomySettingsStore`.
- `app/Actions/Admin/`, `routes/admin.php`: редактор экономики и аудит версий.
- `app/Services/Hearts/ServicePayments.php`: резерв, списание и освобождение согласованной цены.
- `app/Services/Catalogs/`, `database/seeders/CatalogSeeder.php`, `routes/catalogs.php`: общие каталоги, снимки и личные способы.
- `app/Actions/Duties/`, `app/Services/Duties/`, `routes/duties.php`: отдельное выполнение и проверка обязанностей.
- `app/Services/Hearts/`, `app/Actions/Hearts/`: журнал, сверка и ручные операции только действующим партнёром.
- `app/Console/Commands/`, `routes/console.php`: проверка просрочек и сверка балансов; scheduler нужно запускать отдельно.

- `routes/products.php`, `app/Services/Requests/*Attachments.php`: снимки блюд и условий, обещания подарков, денежные реквизиты.
- `app/Services/Media/PrivatePhotos.php`: проверка и закрытая выдача фото каталога.

- `app/Actions/Friends/`, `routes/social.php`: личные друзья и архив.
- `app/Actions/Requests/CreateGuestVisit.php`, `app/Actions/Dates/`, `app/Services/Requests/SocialInvitations.php`: гостевые визиты и свидания с неизменяемыми снимками.
- `docs/friends-guests-dates.md`: правила приглашений, миграция и проверки.

- `app/Actions/Mood/`, `app/Services/Mood/MoodHistory.php`: оценки, запросы настроения и личное расписание.
- `app/Actions/Reminders/`, `app/Services/Reminders/ReminderLifecycle.php`, `routes/care.php`: разовые напоминания, отмена и подготовка доставки; отправитель и scheduler запускаются отдельными процессами.

- `app/Services/Notifications/`, `app/Actions/Notifications/`, `app/Jobs/SendTelegramNotificationJob.php`: транзакционный outbox, проверка источника/пары/привязки, lease и итоги Telegram.
- `app/Services/Telegram/BotClient.php`, `config/services.php`: выключаемый транспорт и секрет защищённого webhook без секретов в логах.
- `app/Services/Scheduling/DueCare.php`, `care:process-due`, `telegram:dispatch-notifications`: локальные часы пары, ограниченный catch-up и восстановление очереди; необязательный `compose.workers.yaml` и отдельные процессы описаны в `docs/telegram-and-scheduling.md`.

- `app/Actions/Auth/Telegram/`, `app/Http/Controllers/Telegram/WebhookController.php`: одноразовая привязка к исходному браузеру и защищённый вход.
- `app/Services/Telegram/CallbackRouter.php`, `KeyboardFactory.php`, `WebhookHandler.php`: одно нажатие с проверкой прав/версий и общие Actions, атомарный receipt без raw update.
- `app/Services/Telegram/ProfilePhoto.php`, `app/Http/Controllers/Auth/AvatarController.php`: импорт проверенного фото и закрытая выдача собственнику.
- `telegram:prune-controls`: ежедневная очистка технических подтверждений через семь суток.

- `public/manifest.webmanifest`, `public/sw.js`, `public/offline-v1.html`, `public/icons/`: установка PWA и ограниченный кэш только публичных версионированных файлов; личные ответы и действия не кэшировать.
- `resources/js/modules/pwa.js`, `resources/views/components/pwa-help.blade.php`: ручная установка, состояние сети, блокировка offline-форм и повторная проверка авторизованной страницы из browser back cache.
- `tests/JavaScript/pwa.test.mjs`, `npm run test:pwa`: проверки политики кэша и клиентского поведения без дополнительных зависимостей; включены в Quality.

- `app/Actions/Auth/Telegram/IssueTelegramLinkCode.php`, `ConsumeTelegramLinkCode.php`, `profile.telegram.link`: одноразовая привязка по UUID из `/start`.
- `app/Services/Telegram/PollTelegramUpdates.php`, `telegram:poll-updates`, профиль Compose `telegram-polling`: приём сообщений без публичного webhook; токен не выводить.

- `app/Services/Realtime/ApplicationChanges.php`, `app/Events/ApplicationUpdated.php`, `routes/channels.php`, `resources/js/modules/realtime.js`: Reverb, закрытый сигнал без личных данных, обновление страниц с сохранением черновиков; отдельный сервис `realtime` в Compose.
- `app/Services/Telegram/MoodStickers.php`, `app/Jobs/DownloadMoodStickerJob.php`, `app/Http/Controllers/MoodStickerController.php`: приватные WEBP/TGS/WEBM для настроения, загрузка вне транзакций.
- `profile.timezone`, `app/Actions/Couples/UpdateTimezone.php`: общий часовой пояс только в подробных настройках профиля.

- `mood.day`, `app/Services/Mood/MoodDay.php`: постоянная история партнёра по локальным суткам со средним и деталями.
- `profiles.avatar`, `profile.compliments.store`: фото текущему партнёру и зашифрованные личные комплименты.
- `app/Actions/Movies/ManageMovieChoice.php`, `movies.selections.*`: совместные сессии, два согласия, первый match и сохранённая рулетка.
- `app/Actions/Duties/CreateDutyReminders.php`, `ManageChoreDraw.php`: дневные напоминания и согласованная жеребьёвка через общие просьбы/обязанности.
