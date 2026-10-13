[← Interface](interface.md) · [Back to README](../../README.md) · [Couples and invitations →](couples.md)

# Accounts and profile

## Features

- `/register`: registration with a name, login and password; email is not required.
- `/login`: sign-in; `/logout`: sign-out, POST only.
- `/profile`: your own profile, personal currency, payment details, Telegram status and links to personal sections; changes are saved with a PATCH request.
- `/recover`: password recovery with a one-time recovery code.
- Sign-in with Telegram and linking from the profile: a one-time browser–bot pairing with two confirmations; see the [full guide](telegram-and-scheduling.md).

A login has 3–40 Latin letters, digits or `_` and is case-insensitive. A password has 12–128 characters, with letters and digits. Feminine, masculine and neutral forms of address are available; they do not affect permissions.

The sign-in survives closing the browser. Every request to the app extends the session by 30 days; after 30 days without activity you have to sign in again. The period applies to each browser separately. The sign-out button ends the session immediately, and password recovery still revokes old sessions. Clearing cookies or using a private window may also require a new sign-in.

In an existing environment set `SESSION_LIFETIME=43200` (minutes) and `SESSION_EXPIRE_ON_CLOSE=false`, then refresh the config cache and restart the app processes. A value from an old `.env` takes priority over the new default; a long-lived "remember me forever" cookie is not used.

After registration eight recovery codes are shown once. Save them before the page is opened again. The database stores hashes of the codes in an encrypted field; the temporary list in the session is encrypted too. A used code is removed atomically under an account lock. After recovery a new sign-in is required, the user's old session records are deleted, and the `auth.session` middleware rejects sessions with the old password hash.

Payment details are encrypted and currently available only to the profile owner. A partner sees a snapshot of the details only in an accepted money request or condition. The administrator, password, payment details, recovery codes and internal photo path fields are not part of the regular user serialization. A client cannot give itself the administrator role.

## Preparing a confirmed environment

Migrations have not been run against a production MariaDB. Before using registration, confirm the environment and back up the existing database. Then, in the chosen local Sail environment:

```sh
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run build
```

On `/register` create an account, save the codes, change the name in the profile, sign out and sign in again. To check recovery, use one saved code on `/recover`; using it a second time must be rejected.

`APP_KEY` is required for encryption. Losing the key makes encrypted data unreadable; keep the secret key outside Git. Regular registration needs no extra `.env` settings; Telegram settings are listed in its guide.

## Data schema

| Table | Changes |
| --- | --- |
| `users` | Nullable email/password; unique nullable login; gender, locale, currency, avatar_path, payment_details, recovery_codes; is_admin=false |
| `telegram_accounts` | Unique user_id and telegram_id; chat_id; timestamps; a foreign key to users that forbids deleting a linked account |

Existing accounts are kept; their new fields may be empty. A new registration explicitly stores the current interface language, the BYN currency and the chosen form of address. Couples are created with the BYN currency. In the profile you can choose a personal currency of BYN, RUB, USD or EUR; it is the default in personal catalogs until you join a couple. Once there is a couple, its currency takes priority in forms. Changing the personal currency does not change the partner, the couple, old amounts or snapshots of payment details; there is no automatic conversion. Sign-in through a confirmed Telegram is available once the bot is configured.

Rolling back the users extension is refused while there are accounts without an email or password: restore a backup first or fill in real values. The rollback removes the new columns and the identifier table; save related data before rolling back. An empty rollback does not restore their contents.

## Checks

Automated tests use only in-memory SQLite. They cover registration without email, password hashing, the sign-in attempt limit, session rotation, CSRF, profile ownership, encryption, Telegram ID uniqueness and the one-time recovery code. `AdditionalSettingsScreensTest` covers the personal currency, existing money amounts staying unchanged, saving without a currency, field errors, privacy of links and administrator access. Concurrent sign-in completions and registering one Telegram identity are covered by `TelegramConcurrencyTest` in a separate MariaDB.

## See also

- [Development and checks](development.md)
- [Interface](interface.md)
- [Telegram and scheduling](telegram-and-scheduling.md)

## Your own profile photo
Tap the avatar in the profile and choose a JPEG, PNG or WebP up to 4 MB: the upload starts right away, with no separate save. The photo is checked by content and dimensions (up to 4096×4096), converted to a JPEG of up to 1200 pixels and stored in a private directory. Only the owner can upload and read their avatar. A new photo replaces the one imported from Telegram; the old file is deleted after a successful save. A failed upload keeps the previous photo. No migration is needed: `users.avatar_path` is used.

## Partner photo and compliments (0.22)
`profiles/{user}/avatar` serves the private photo only to its owner or the current partner, with private/no-store. Access ends when the couple ends. On the home screen the photo replaces the partner's letter avatar.
In the profile you can save up to 50 personal compliments of 300 characters each: an empty line deletes one, and a duplicate is merged. `users.compliments` is stored as encrypted:array and hidden from serialization. On the current partner's home screen the text changes every five minutes; with an empty list the previous phrase is used. Compliments are not sent to Telegram automatically.

## Profile and partner view (0.23)
The main block holds the photo, name, a bio of up to 500 characters and the number of recipes. Editing the main data expands inside the block. Currency and payment details are saved separately and do not overwrite the name or bio; changing the main data keeps the payment details. Telegram and technical settings are collapsed.

`GET profiles/{user}` shows only the current partner's profile: name, bio, photo and number of recipes. Payment details, recovery codes and compliments are not shown. Access ends after the couple ends. On the home screen the partner's avatar and name lead here. `users.bio` is nullable encrypted text.

## Interface language (0.32)
The interface is available in English and Russian. The default language is set by `APP_LOCALE` (`en` in `.env.example`). In the profile, under "Language", a user picks their own language: `PATCH locale` saves it to `users.locale` and to the `locale` cookie of that device. Guest pages need the cookie: sign-in and registration open in the language of the device's last user, and they have a switch as well. An unknown value is rejected by validation.

A signed-in user's language takes priority over the cookie. Telegram notifications and bot replies are written in the recipient's language, not in the language of whoever triggered the event. Names, descriptions and other texts people typed themselves are not translated. The starter catalogs are stored in Russian and translated when shown.

Translation keys are the Russian phrases from the code; the English texts live in `lang/en.json`. `LocaleTest` checks that every interface string has an English text with the same placeholders. Existing accounts keep the `ru` language. No migration is needed: `users.locale` is used.
