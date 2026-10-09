# Home Assistant repository

This workspace is connected to https://github.com/ZeroKJ123/Home-Assist.git.

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
| Development | docs/development.md | Проверки и изоляция тестов |
| Interface | docs/interface.md | Компоненты и доступность |
| Accounts | docs/accounts.md | Регистрация и миграции профиля |
| Couples | docs/couples.md | Пары, приглашения и согласование недельного лимита |

## Project entry points

- `app/Http/Controllers/HomeController.php`: стартовая страница.
- `resources/views/layouts/app.blade.php` и `resources/views/components/`: каркас интерфейса.
- `tests/Support/TestEnvironment.php`: обязательная защита тестовой базы.
- `.github/workflows/quality.yml`: проверки без деплоя.

- `app/Actions/Auth/`, `app/Data/Auth/`, `routes/auth.php`: аккаунты и восстановление.
- `app/Actions/Profiles/`, `app/Policies/UserPolicy.php`: собственность профиля.
- `lang/ru/validation.php`: русские сообщения проверки ввода.

- `app/Actions/Couples/Agreements/`, `routes/agreements.php`: версии недельного лимита и подтверждения.
- `tests/Support/ConcurrentOperations.php`: общий протокол гонок в изолированной MariaDB.
