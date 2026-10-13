[← Duties and hearts](hearts-and-duties.md) · [Back to README](../../README.md) · [Kitchen, wishes and money →](dishes-wishlist-money.md)

# Economy and catalogs

Version 0.6.0 adds an editor for the economy rules, a reserve for the service price and starter categories/templates. Picking from a catalog does not mean agreement and creates no reward.

## Administrator rules

The `/admin/economy` editor is available only to an account with `is_admin`; the link is in the profile. Public registration cannot assign an administrator. Assigning the role in a production environment remains a separate action that the owner has to allow.

`config/economy.php` is used once, for the first version: on initial seeding or on the first access to the rules. After that, config changes do not affect the rules in force. Every change in the editor saves a new version, its author and the time; earlier versions are protected by the model from updates and deletion. Privileged SQL is not limited by this protection.

Numbers are in the range 0–1000; the cap is strictly positive and not below the starting amount. A single reward does not exceed the daily limit, and daily limits do not exceed weekly ones. An explicit confirmation and the current version are required. Repeating the same form does not create a second version; a competing change is rejected. Saving unchanged values creates no version.

The refusal rate is fixed when a request is sent, and the reward and penalties when it is accepted. A new version does not rewrite these snapshots, an agreed price or the past ledger. Current limits apply to new operations. Lowering the cap does not reduce an existing balance; changing the starting bonus does not credit it again.

The editor groups settings by balance, rewards, thanks, manual deductions and penalties; all fields are saved as one confirmed version. Errors are shown next to the fields, and the version history expands separately. The link is available from the administrator's profile, and permissions are checked again on the server.

## Service price and reserve

The author sets an integer service price: 0 means a free request. Free hearts are checked before sending and before acceptance; sending itself reserves nothing. The reserve appears only when the recipient agrees or when the author separately accepts a counter-condition. The agreement, the duty and the reserve are atomic.

| Event | Result |
| --- | --- |
| Mutual agreement | `held`: the price holds part of the balance; no deduction entry is created yet |
| The performer marked completion | The reserve stays until the check |
| The partner confirmed the service | `settled`: the price is deducted once and the reserve is closed |
| The service mark is disputed | `released`: the reserve is released; the performer's penalty is applied separately |
| Mutual cancellation or the couple ending | Remaining reserves are released |

Free balance = total balance − active reserve. Penalties are kept in full and can make the free part negative, but they do not destroy an accepted reserve. Example: balance 20, reserve 15, penalty 30 → total −10, free −25, reserve 15. Checking the service then deducts 15 → total −25, reserve 0. A new paid service cannot be agreed without enough free hearts; a regular free request stays available. "Really want" needs a non-negative free part.

The price is burned and not transferred to the performer. Their reward for a confirmed service is calculated separately, with limits. Checking a counter-condition does not deduct the service price. Before agreement the author cancels; after agreement one member suggests cancelling and the other confirms. A completed confirmed duty cannot be cancelled. Cancellation closes unfinished duties and does not refund penalties applied earlier.

## Catalogs and personal methods

The starter data has eight expense categories Т1–Т8, eight request categories О1–О8, 31 templates and seven agreement methods. The templates include household chores, care, real kisses, TikTok, guests, shared events, money and a gift. The TikTok catalog is not connected to an account and does not verify viewing; the members describe a period or a selection.

Shared methods: money, kisses, hearts, an action, a wishlist gift, sushi and a custom option. Counter-conditions support an action, real kisses, sushi, money, a wishlist gift and a custom option. Kisses have no virtual wallet. Sushi requires the sizes `small`, `medium`, `large`; they do not set a price. [Dishes, wishes and money requests](dishes-wishlist-money.md) are already connected. A text request for food and a regular dish are free. Buying a gift and a money payment earn no reward.

When creating a request press "Apply the template", then refine the description and the deadline. The template and the category are saved as a snapshot of their names. A counter-condition method is saved in its version together with the description and the size; a later catalog edit does not rewrite the agreement.

Custom methods are available only to their owner and are hidden even from the administrator of another account. The name and details are encrypted in the database and never reach the logs. The partner sees the sent condition with the selected details. The limit is 100 custom methods per account; repeating a submission with the same key and data does not create a duplicate.

The starter catalogs are stored in Russian. Their names are translation keys, so they are shown in the reader's language; names you type yourself are shown as written.

## Applying and schema

Production migrations and initial seeding were not run automatically in this block. In an agreed environment, after a backup and saving `APP_KEY`:

```sh
php artisan migrate --no-interaction
php artisan db:seed --class=EconomySettingsSeeder --no-interaction
php artisan db:seed --class=CatalogSeeder --no-interaction
```

In production the usual Artisan confirmations need a separately allowed run with `--force`. Do not run the general `DatabaseSeeder` on a production database: it contains a demo user. Repeating the two specialized seeders creates no duplicates, does not rewrite economy versions and creates no duties or hearts. CatalogSeeder restores the standard names by their codes and does not change custom methods. The encryption key is needed already when seeding the catalog.

| Migration | Changes |
| --- | --- |
| `2026_10_09_231700_create_economy_settings_tables.php` | `economy_settings_versions`: unique version, JSON of the rules, author, time; `economy_settings`: a pointer to the version in force, the canonical row with ID 1 |
| `2026_10_09_231701_create_heart_reservations_table.php` | The price and the member who suggested cancelling, on the request; `heart_reservations`: unique request, wallet, amount, status and settlement/release time |
| `2026_10_09_231702_create_catalog_tables.php` | Expense/request categories, templates, shared/personal methods; snapshots of names and size in requests/conditions |

Historical requests get a price of 0 with a single update `WHERE heart_price IS NULL`; no reserves or new duties are created for them. Before applying to a large production table, estimate the row count and the lock time: the migration was checked only on isolated test schemas. The application passes new values explicitly. Unique keys protect versions, reserves, codes and repeated creation; indexes support a wallet's active reserves and personal methods.

Roll the schema back in reverse order: catalogs → reserves → settings. `down()` drops the new tables and columns together with their data and does not return hearts deducted earlier. Restoring needs a verified backup, the earlier encryption key and an agreed restore procedure; an empty rollback does not restore the history.

## Checks and diagnostics

SQLite checks permissions, versions, immutability, deadlines, repeated operations, a negative free part, cancellation, atomic rollback and snapshots. MariaDB checks competing settings, two orders against one balance, a penalty at the same time as a deduction and cancellation at the same time as confirmation. Logs hold only technical IDs, versions, states and amounts; personal texts and encryption keys are not written.

On a version error refresh the page and confirm the new data. When free hearts are short, complete or mutually cancel earlier services. An empty shared catalog means its specialized seeder has not run yet; do not point the tests at a production database to fill it.

## See also

- [Duties and hearts](hearts-and-duties.md)
- [Requests and conditions](requests.md)
- [Development and checks](development.md)

## Movies and fill-ups
The home screen offers "Movies" and "My cars". Movies keeps a personal watch list: you can mark favourites (they come first), update an entry by adding the same title and year again, or archive it. The couple pick combines the current lists without duplicates: the normalized title and the year are compared. A right swipe or the "Want to watch" button saves a like; left or "Skip" saves a skip. A match appears after two likes. A saved vote cannot be replaced by resubmitting. The roulette picks a random movie from mutual matches or from all of the couple's movies — the mode is chosen explicitly. A former partner has no access to new personal movies after the couple ends. Without JavaScript regular buttons are available, and the animation respects reduced motion.

`movies`: the owner, a title/year fingerprint, encrypted title and note, year, favourite, archive. Unique on `(user_id, identity_hash)`. `movie_votes`: couple, user, fingerprint, like; unique on `(couple_id,user_id,identity_hash)` with a match index. Personal lists and matches are paginated.

Adding a car saves a personal name and a fuel type (petrol, diesel, gas or charging) and creates a custom `fuel` method in the condition catalog. Choose it in the answer to a request and state the volume or the budget in the description. The partner must accept the condition separately. A car and its method are available to the owner; another user cannot offer someone else's method. The name and details are saved in the snapshot of the sent condition. A fill-up earns no reward for a counter-payment and makes no bank transfer. `cars`: user, unique method, submission UUID, encrypted name and fuel; unique on `(user_id,operation_key)`.
