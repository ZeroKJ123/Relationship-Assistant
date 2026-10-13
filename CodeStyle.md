# Code Style — Relationship-Assistant

## 0. General principles

### 0.1 One style
The code should look as if one person wrote it: clear, readable, structured, fault-tolerant and easy to maintain.

### 0.2 OOP and SOLID
We write object-oriented code and follow SOLID. The canons need not be followed dogmatically, but the architectural idea must be respected.

### 0.3 Simplicity over haste
We do not settle for the first working solution. Complex constructs are simplified, and repeated code is extracted.

### 0.4 Reuse
If the same logic is used more than once, extract it into a separate method, service, trait or object. Do not merge different business rules just because they look alike, and do not create abstractions with no real use.

### 0.5 `strict_types`
Rule: `declare(strict_types=1);` is required in every regular PHP file of the project, with no exceptions.

Before adding strict_types to a new or refactored file, run the safety check (see 0.7).

strict_types does not apply to:
- Blade templates (`.blade.php`)
- Auto-generated cache files (`bootstrap/cache/*.php`)

### 0.6 Style automation
Formatting and part of the mechanical rules must be checked automatically with `Laravel Pint`.
Static typing and type quality must additionally be checked with `PHPStan`.

### 0.7 Safety check before adding `strict_types`

Before inserting `declare(strict_types=1);` into a specific file, check:
- scalar arguments (method calls with `int`/`float`/`string`/`bool` coming from outside),
- nullable flows (`?type`, `null` by default, `??`),
- explicit type casts (`(int)`, `(string)` and so on),
- calls to `config()`, `session()`, `request()` — they return `mixed`,
- array access (`$array['key']` may return `mixed`),
- `json_encode()` / `json_decode()` — they return `string|false` and `mixed`,
- model attribute conversions (Eloquent `getAttribute()` returns `mixed`),
- framework expectations (callback signatures in routes, middleware, event listeners).

If the file has a place where strict_types would lead to a `TypeError`, fix the call first (an explicit cast, input validation, wrapping in a DTO), then add strict_types. Do not leave files without strict_types — that is technical debt, not a solution.

## 1. Control structures

### 1.1 `if`, `else`, `elseif`
Prefer to start from the opposite case and use an early `return`.
Avoid long conditions inside `if`.
The maximum allowed nesting is two levels.
`else` and especially `elseif` are strongly discouraged: in most cases the code can be simplified with an early exit.

### 1.2 `switch`, `match`
If the same variable is checked against many options, prefer `match` or separate handlers over a long chain of `if`. Use typed enums for statuses.

### 1.3 `match`
Use `match` when a value has to be returned or assigned with no extra side effects.
Remember that `match` uses strict comparison `===`.

### 1.4 `switch`
`switch` uses loose comparison `==` and conflicts with rule 2.9. In new code use `match`, early exits or separate handlers. When changing an existing `switch`, preserve its behaviour with tests and replace it with a strict construct.

## 2. Working with data

### 2.1 Strings: concatenation
Concatenate strings with `sprintf()` or with a string literal that has mandatory curly braces around variables.

### 2.2 Heredoc
If a large SQL statement has to be put into a variable, use `heredoc`.

### 2.3 Hard-coded values
"Magic" numbers and strings must not be left unexplained.
Any value must either live in a clearly named variable or come with a comment.

### 2.4 Money calculations
Money calculations use `BigDecimal` (`brick/math`), with no `float`. Amounts are stored as exact decimal values with a currency; validated decimal strings are used at the boundaries. Integer counters, including hearts, stay integers.
For money calculations set the scale and the rounding mode explicitly when an operation needs rounding.

### 2.5 Rounding
The rounding mode is determined by agreed domain logic and covered by tests. The "fees up, totals down" rule of the original financial project is not carried over automatically: Relationship-Assistant makes no bank transfers and charges no fees. No automatic currency conversion is done without a separate requirement.

### 2.6 Arrays: safe access
If a key is not guaranteed to exist or its absence is acceptable, use safe access through `Arr::get()` or `??`, when a missing key and `null` may be treated the same.

### 2.7 Arrays: required access
If the key must exist, access it directly with `[]` so as not to hide an error in the data.

### 2.8 Collections
Collections are preferred over plain arrays when working with a set of entities and further transformations.

### 2.9 Comparison
Always use strict comparison.
If the type is not obvious, cast the value to the needed type first, then compare with `===` or `!==`.

### 2.10 DTO
DTOs carry data between layers and processes.
A DTO must not contain business logic: only data, input conversion and serialization.

### 2.11 Nullable types and handling `null`
- For nullable parameters and return values use the `?Type` notation.
- Do not use `Type|null` in signatures where `?Type` works (one style).
- For union types where `null` is one of the options, use the native union syntax: `string|int|null`.
- Before using a nullable value, check for `null` explicitly or use `??` / `?->`.
- Do not rely on implicit conversion of `null` to `''`, `0` or `false`.

## 3. OOP

### 3.1 Classes
Class names are written in `PascalCase`.
A class name should describe an entity and usually be a singular noun.

### 3.2 Constructors
The constructor is responsible for setting up the object.
If the parent class has a constructor, call it according to its contract, then do the dependent initialization of the child class. Keep in mind that PHP promoted properties are initialized before the constructor body runs.

### 3.3 Constructor promotion
If constructor parameters are simply stored in properties, prefer property promotion.

### 3.4 `final`
If a class or method must not be extended or overridden, mark it `final`.

### 3.5 Inheritance
Use inheritance deliberately, only when the child class really extends the parent's behaviour.

### 3.6 Methods and properties
Names must be as clear as possible and reflect the purpose.
Boolean properties and methods start with `is`, `has` or `can`.
Methods should describe an action and start with a verb.

### 3.7 Visibility
Every property and method explicitly declares `public`, `protected` or `private`.

### 3.8 `static`
Static methods are acceptable when the work happens in the context of the class.
Static properties are not used: they often lead to memory leaks and implicit state.

### 3.9 Named arguments
If a method has many parameters and some of them have defaults, use named arguments.

### 3.10 Constants
Immutable values are extracted into constants.
Constant names are written in `MACRO_CASE`.
If the meaning of a value is not obvious, add a comment.
In code use class constants, not "raw" numbers.

### 3.11 Interfaces
Interfaces describe a contract.
An interface name should be an adjective, or a noun if that is unnatural.

### 3.12 Marker interfaces
An empty interface is acceptable when it marks belonging to a type or a pattern.

### 3.13 Traits
Regular traits are named with the `Trait` suffix.
Traits are used for shared reusable logic and to reduce duplication.

### 3.14 Abstract classes
Abstract classes are named with the `Abstract` prefix.
If an abstract class has no abstract method at all, an interface is probably what is needed.

### 3.15 Order of class elements
Try to keep one order of class elements.
When the environment is prepared, the canonical order is fixed in `pint.json` (`ordered_class_elements`):
`use_trait`, `case`, constants, properties, `construct` / `destruct`, magic methods, `phpunit`, abstract methods, `public static`, `public`, `protected static`, `protected`, `private static`, `private`.

In documents and reviews refer to `pint.json` as the single source of the order.

### 3.16 Typing and PHPDoc
- In PHP 8.4 prefer native type declarations over PHPDoc wherever possible.
- Use PHPDoc for:
  - generic collections and complex arrays (`array<string, mixed>`, `Collection<int, User>`),
  - complex union/intersection types that cannot be expressed natively,
  - `@throws` to document exceptions,
  - `@property` / `@method` for Eloquent models (Larastan).
- PHPDoc does not duplicate native types: if a parameter already has `string $name`, do not write `@param string $name`.
- Write `@return` in PHPDoc only when the native return type is not enough (generic, mixed, a complex union).
- Do not leave empty PHPDoc blocks (`/** */`) and do not duplicate docblock opening tags.

## 4. Database

### 4.1 Migrations
Any change to tables and columns is made only through migrations.
`down()` must be the inverse of `up()` for reversible changes. Lost data cannot be restored by an empty rollback: irreversible changes are documented explicitly, with a plan for keeping the data and a restore check before applying. Do not claim reversibility where there is none.

### 4.2 Updating data in migrations
If data has to be changed, estimate the volume and the effect on locks.
Heavy and long updates are moved out of the migration into a temporary command when possible.

### 4.3 Database replica
Code that updates data must not run on a replica.
Before bulk changes check which connection is used.

### 4.4 Indexes
If a column is often used in `WHERE`, `JOIN`, sorting or selection and this affects performance, add an index.
But do not create indexes thoughtlessly: they slow down `INSERT`, `UPDATE` and `DELETE` and take space.

### 4.5 Default values
Database defaults are allowed mainly for boolean fields.
For other fields pass values explicitly, so as not to create "magic".

### 4.6 Transactions
If an operation makes several consistent writes to the database, it must be wrapped in a transaction.
Keep inside the transaction only what really belongs to changing the data. HTTP calls, sending to Telegram and file processing are moved outside the transaction; notifications are sent after commit through a durable event queue.

### 4.7 Database documentation
Changes to the database structure, statuses, columns and other schema must come with a documentation update, if the project keeps one.

## 5. Laravel

### 5.1 Models
A model is named in the singular.
Keep in the model only what belongs to its structure: traits, `fillable`, `casts`, relations.
Other large logic is extracted.

### 5.2 Model helper traits
For large models the logic may be split into `Constants`, `Methods` and `Scopes` traits.
Such traits are named after the model, without the `Trait` suffix.

### 5.3 Factories
Every model must have a factory.
When adding columns, do not forget to update the factory.

### 5.4 Relations
Relations are described by Laravel's conventions.
Names of "to one" relations are singular, and "to many" plural.
Do not spell out foreign keys manually without need.
Every relation declares its return type.

### 5.5 Scopes
Scopes are used to remove query duplication.
If a scope adds fields to the selection, it must start with `with...` and use `addSelect`.

### 5.6 Casts
Casts are declared where a field must always be converted to a certain type.
For money and computed values casts are required if they are part of the domain model.

### 5.7 Controllers
Controllers are named `<Entity>Controller`.
A controller should be simple and oriented around CRUD actions: `index`, `store`, `show`, `update`, `destroy`.
Business logic is moved out of the controller into domain Actions and services. For separate commands a controller with `__invoke()` is acceptable: do not force state transitions into an artificial CRUD.
If one entity is served separately in the Admin and Client parts, the controllers go into different folders.

### 5.8 Dependency Injection
Laravel's DI is used everywhere: in controllers, services, commands and jobs.
Services are injected into controllers through the constructor.

### 5.9 Race conditions and locks
If conflicting requests are possible, use locks.
When working with a lock, do not rely on method arguments injected beforehand if the state could have changed before the lock was taken.

### 5.10 `whereRelation`
For simple conditions on related models prefer `whereRelation()` over `whereHas()`.
Keep `whereHas()` for more complex callback scenarios.

### 5.11 Routes and URLs
URLs in routes are written in `kebab-case`.
Routes define the HTTP action first, then the name.
Paths inside groups do not start with `/`, except the root `'/'`.
Routes are grouped by prefixes and entities.

### 5.12 Services
Domain Actions implement specific operations, and services hold shared business logic. Site controllers, Telegram and jobs call the same operations; rules are not duplicated between channels.

### 5.13 Logic services
Logic services implement domain and business logic.

### 5.14 Client services
Client services are responsible for integrations.
Their failure must not break the application without an explicit reason.
Turning an external client on and off must be controllable through config.

### 5.15 Requests
Request classes are named with the `Request` suffix.
Validation rules are written as an array, not a string, when that can be done readably.
If a structured set of data is needed from a request, add a `getData()` method.

### 5.16 Resources
JSON responses with model data are returned through a `Resource`; Blade pages are returned through a view, and regular forms through a redirect. Do not create a Resource for an HTML page.
Relations inside a resource are exposed only through `whenLoaded()`; the list of fields is set explicitly, with no leak of personal data.

### 5.17 Commands
Commands are named `ActionEntityCommand`.
The command signature should reflect the action and the entity, but without the `Command` postfix.
One-off temporary commands go into `Temp`.

### 5.18 Jobs and queues
Jobs are named with the `Job` suffix.
The queue is chosen by priority and purpose.
Only serializable data is passed to a job constructor.
Models are converted with `withoutRelations()` before being sent to a job.

### 5.19 Job middleware
For limits and deduplication use job middleware such as `RateLimited` and `WithoutOverlapping`.

### 5.20 `RateLimited`
For a job with `RateLimited` configure `tries` and `maxExceptions` correctly, so that rate limiting does not cause false failures.

### 5.21 Deploying queues
If the project uses Supervisor rather than Horizon, a new queue must be recorded in the deploy instructions when it is added.

### 5.22 Providers
Providers are named with the `Provider` suffix.

### 5.23 `RouteServiceProvider`
New route patterns are constrained by the router so that an invalid identifier gives `404`, not `500`. In the current Laravel 13 use the project's actual structure: routes, `AppServiceProvider` or an explicitly registered provider. Do not create a missing `RouteServiceProvider` just for the sake of a name from another project.

### 5.24 Morph map
All morph relations must be registered through an alias.
When adding a new morph entity, update the morph map in `AppServiceProvider` or in a specialized provider registered in `bootstrap/providers.php`. The name `RelationServiceProvider` does not imply that such a class exists in Relationship-Assistant.

### 5.25 Mocks and fake services
If a service cannot simply be disabled by config and is needed in tests, create a fake implementation and swap the binding in the container.

### 5.26 Creating objects
Objects are created through `app()` or `new`, depending on whether the container and dependency resolution are needed.

### 5.27 Imports
Imports must be ordered and contain no unused dependencies.
Classes, functions and constants from the global namespace are imported explicitly when that improves readability.

## 6. Tests

### 6.1 Tests are required
Before handing a task over for review, check that the tests pass and the new functionality is covered by tests.

### 6.2 Money and hearts
For money, hearts and obligations check boundaries, the couple's permissions, repeated events, concurrent operations, deadlines and confirmations. Checks must catch behaviour errors, not repeat the implementation.

### 6.3 Simple tests
Even complex tests must stay understandable.
Follow the KISS principle.

### 6.4 Test structure
A test file must have a clear name with the `Test` postfix.
Folders are built by entity and layer, for example `Models/...` or `Services/...`.

### 6.5 `it` instead of `test`
Use `it()` as the single style of writing tests.
The name must clearly describe the behaviour. Tests run on Pest, with PHPUnit as its execution base.

### 6.6 One case per test
One test checks one main scenario and related negative cases only within that scenario.
If other behaviour is affected, it needs a separate test.

### 6.7 Factories
Models and input data for tests are created through Laravel factories.

## 7. Release and deploy

### 7.1 Simple release actions
If a release needs one simple operation, do not create a separate command.
The instruction must contain the specific operation, the conditions for running it and a check of the result. For a simple permitted action `tinker` is acceptable; this is not permission to change an unknown production database or run arbitrary code.

### 7.2 The Deploy field in a task
The deploy instruction lists only special actions:
`.env` keys, temporary commands, the order of running them, actions of other people and other non-standard steps.
Repeated basic steps are moved into a general release instruction; the task refers to it and lists its own special steps. Migrations and data actions must stay visible in the general instruction and be run in a confirmed environment.

### 7.3 Release commands
If the project has a separate layer of release commands, use it for commands that must run exactly at the moment of release.

### 7.4 Release folders
Release folders are named in the format `/Commands/Releases/release_ddMMYY`.

### 7.5 Naming release commands
A release command class starts with `Release`.
The command signature starts with `release:`.

## 8. Working on a task

### 8.1 Starting a task
Before starting, read the task carefully, think through a plan and ask questions if something is unclear.

### 8.2 Clarifying a task
All requirement changes and agreements that appear during discussion must be reflected in the task itself.

### 8.3 Team discussion
If a question affects many people or needs several opinions, bring it to a general discussion instead of deciding alone.

### 8.4 Compatibility with the mobile app
If the backend serves several client versions, take that into account before implementation starts.
An old version of the app must not be broken without an agreed plan.

### 8.5 Doing the work
Before implementing, check whether someone has solved this problem before.
Do not reinvent the wheel if the project already has a suitable pattern.

### 8.6 Tests and documentation
While working on a task do not forget about tests and, on schema changes, about updating the database documentation.

### 8.7 Finishing a task
Before moving a task to review:
wait for the pipeline,
check the deploy instructions,
review the code yourself.

## 9. Naming

### 9.1 Controllers
Named in the singular: `ArticleController`, not `ArticlesController`.

### 9.2 Route URLs
Use the plural: `articles/1`, not `article/1`.

### 9.3 Route names
Use `snake_case`: `users.show_active`.

### 9.4 Models
Models are named in the singular: `User`.

### 9.5 `hasOne` and `belongsTo` relations
The relation name is singular: `articleComment`.

### 9.6 Other relations
Names of collection relations are plural: `articleComments`.

### 9.7 Tables
Tables are named in the plural and in `snake_case`: `article_comments`.

### 9.8 Pivot tables
A pivot table is named with the model names in alphabetical order and in the singular: `article_user`. For a justified deviation set the table name explicitly in the relation.

### 9.9 Columns
Columns are named in `snake_case` and without repeating the model name: `meta_title`.

### 9.10 Model properties
Model properties are used in `snake_case`: `$model->created_at`.

### 9.11 Foreign keys
The foreign key format is `<model>_id`, for example `article_id`.

### 9.12 Methods
Methods are named in `camelCase`.

### 9.13 Resource controller methods
Use the standard CRUD names: `store`, `update`, `destroy`, not arbitrary variations.

### 9.14 Test methods
Test methods and descriptions are written so that they read as behaviour; if method naming is used, it must be in `camelCase`.

### 9.15 Variables
Variables are named in `camelCase`.

### 9.16 Collections
A collection should be named descriptively and in the plural: `$activeUsers`.

### 9.17 Objects
A single object is named descriptively and in the singular: `$activeUser`.

### 9.18 Config and translation keys
Use `snake_case`. Interface texts are the exception: their translation key is the Russian phrase itself, passed to `__()`, with the English text under the same key in `lang/en.json`.

### 9.19 Blade/View files
View file names are written in `kebab-case`: `show-filtered.blade.php`.

### 9.20 Configs
Configuration files are named in `snake_case`: `google_calendar.php`.

### 9.21 Interfaces
An interface is named as an adjective where possible: `Authenticatable`.

### 9.22 Traits
A trait name is an adjective with the `Trait` suffix: `NotifiableTrait`.

### 9.23 Seeders
A seeder is named in the singular: `UserSeeder`.

## 10. HTTP response codes

### 10.1 Constants instead of numbers
In every `abort()`, check and response use HTTP codes through the constants of `Illuminate\Http\Response`, not "bare" numbers.

### 10.2 Importing the class
Before using the codes, check that exactly `Illuminate\Http\Response` is imported.

### 10.3 Choosing a code
The response code must match the meaning of the situation:
`200` for a successful read,
`201` for creation,
`204` for a successful response with no body,
`400` for a bad request,
`401` for an unauthenticated user,
`403` for forbidden access,
`404` for a missing resource,
`422` for validation errors,
`429` for a rate limit,
`500+` for server errors.

## 11. Automated checks

### 11.1 `Laravel Pint`
Code formatting is checked through `pint.json`.
The autoformatter's rules must support this document, not conflict with it.

### 11.2 `PHPStan`
Static analysis is checked through `phpstan.neon`.
New code should aim for explicit types, minimal `mixed` and predictable return values.

### 11.3 What the automated style must catch
Automation must support at least:
`strict_types`,
strict comparisons,
removal of needless `else`/`elseif`,
import order,
order of class elements,
uniform formatting of call chains and multi-line arguments.

### 11.4 The mandatory gate before a push
Before every push check the whole outgoing range of commits against this document. For code changes successful Pint, PHPStan/Larastan and the applicable tests are required; for the interface, the build as well. The results apply to the final version: after fixing code, repeat the checks.

If a check found a violation, failed, was skipped or is unavailable, do not push the code. Do not disable checks for the sake of a push and do not substitute their result. Keep the work locally and report the reason. For a documentation-only range, checking the content, links, formatting and `git diff --check` is enough. In the report state the checks and the result of the push.

## 12. How to refer to this document

### 12.1 Reference format
In reviews and comments use the format:
`Does not comply with 1.2`
`Violates 3.1 and 5.7`
`Check 2.9, 4.6, 10.1`

### 12.2 Priority
Explicit instructions of the owner and security constraints take priority. For technical decisions this document is the project standard; the architecture and tool settings must comply with it. General Laravel/PHP recommendations apply where the document sets no rule.

A conflict with generated Boost guidelines or a tool configuration is recorded and resolved before the dependent change. An exception must state the reason, the scope and the owner's agreement; a silent deviation is not acceptable. This document gives no separate permission to develop, deploy, change production data or send messages to people.

## 13. Product and interface rules

### 13.1 Blade, JavaScript and CSS
Blade is responsible for display: no database queries and no business logic in templates. Repeated cards, forms and dialogs are components. JavaScript is small modules with explicit loading, error and success states, with no duplication of server rules.

Colours, spacing, radii and typography are set by the shared tokens of the chosen playful design. Do not copy arbitrary values between screens. Names, avatars, the balance and dates come from data; the demo Alexey/Sofia do not become interface constants. Texts are available for localization.

### 13.2 Accessibility and the mobile screen
Use semantic elements, a label for every field, a visible focus and keyboard control of dialogs. A status is conveyed by text, not by colour alone. Check a narrow screen, long names, empty lists and an unavailable network. A repeated click does not create a repeated operation; confirmations of important actions are kept on the site. By the owner's explicit decision of 11 October 2026, Telegram performs actions with one protected press and no second confirmation; the reason for the exception is to remove an extra step in the bot. Checks of permissions, the deadline, the version and repeats are mandatory in both channels.

### 13.3 Hearts, deadlines and permissions
Rewards, penalties and limits come from versioned system settings, not from numbers in a controller. Every heart operation has a reason, a source and protection against being applied twice. Recalculating the balance, checking limits and writing the ledger are atomic; the current state is read under a lock.

Accepting a condition does not mean it was fulfilled. A reward appears only after the required confirmation. The "No money for groceries" exception is not penalized. All operations check membership in the couple on the server; an identifier from a form or a Telegram button does not prove the right of access.

### 13.4 Performance
Lists are limited by pagination; N+1 is removed with meaningful eager loading. Indexes are chosen by real queries, and heavy tasks run in a queue. Images are checked, limited in size and served in suitable sizes with access control. A cache is added together with invalidation rules; keys take the user or the couple into account. Optimization is confirmed by measurements, and code is not complicated by assumptions.

### 13.5 Diagnostics and secrets
Logs are structured: the operation, the technical event identifier, the status transition and the reason for an error. The level is controlled by the environment. Do not write passwords, tokens, payment details, texts of personal requests, photos or full webhook payloads. For expected refusals use domain errors; unexpected errors are not hidden by an empty catch.

### 13.6 The mandatory check before finishing
Every implementation task includes a self-check against the applicable CodeStyle items, Pint, PHPStan/Larastan, meaningful automated tests and a documentation update. When the interface changes, also check the build and the match with the mockups. Checks run in an isolated environment; a skipped or unavailable check is stated explicitly and is not counted as passed.
