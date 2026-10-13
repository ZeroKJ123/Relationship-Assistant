[← Mood and reminders](mood-and-reminders.md) · [Back to README](../../README.md)

# Telegram and scheduling

Notifications about requests, answers, marks and checks of duties, overdue duties and mood are saved together with the action. A bot failure does not undo a saved action. One-off reminders are delivered to the recipient's private Telegram, and mood questions to the schedule's owner. Both members are notified about an overdue duty.

Sign-in and browser–bot linking, a protected webhook and quick actions with a separate confirmation are available. Automated checks use test accounts and fake API responses. For running on a laptop, messages can be received through polling; a public webhook is not needed then.

## Linking with a code from the bot

1. In the bot's private chat send `/start` with no extra parameters.
2. Press the button with the UUID under the reply — Telegram copies the code. If an old client shows no button, copy the code from the message text. The code is valid for 10 minutes and works once; a new `/start` replaces the previous code.
3. Sign in to your own app account, open the profile and paste the code in the "Telegram link" section. Press "Link Telegram".
4. The profile shows "Telegram linked". Notifications and reminders will be sent to this private chat through the existing queue.

The code links Telegram to an already registered account and does not create an app account by itself. Enter only a code received in your own chat. A linked Telegram and a linked user cannot be reassigned this way; `/start` in a linked chat reports the existing link. If the code expired, send the command again. Linking is protected by sign-in, CSRF and an attempt limit. The code is not returned to old form values after an error.

`telegram_link_codes` stores a SHA-256 fingerprint of the UUID, an identity fingerprint, the encrypted Telegram ID, the expiry and the time of use. There is one current code per identity. On use the encrypted identity is deleted. Creation and acceptance use the same `telegram_auth_locks` as the earlier linking, so concurrent attempts cannot get around account uniqueness. A repeated Telegram update returns the saved encrypted reply and keeps the UUID; it does not issue a second code. The cleanup command deletes old records seven days after expiry. Rolling back the new migration deletes only the history of these codes and leaves linked `telegram_accounts`.

## Receiving messages on a laptop

For the bot set in the private `.env`:

```dotenv
TELEGRAM_ENABLED=true
TELEGRAM_MODE=polling
TELEGRAM_BOT_USERNAME=bot_name_without_the_at_sign
```

`TELEGRAM_BOT_TOKEN` is stored there too; do not print it in commands or logs. With polling `TELEGRAM_WEBHOOK_SECRET` is not needed. The laptop must have outgoing access to `api.telegram.org`; the phone opens the app at the usual local IP/port. Start the separate receiver:

```sh
docker compose -f compose.yaml -f compose.workers.yaml --profile telegram-polling up -d telegram-poller
docker compose -f compose.yaml -f compose.workers.yaml restart telegram-worker scheduler
```

`telegram:poll-updates --once` checks one batch for diagnostics; without `--once` the command runs permanently. Before starting it checks that there is no webhook, and it does not delete an existing webhook automatically. `getUpdates` and a webhook are mutually exclusive ways of the [Bot API](https://core.telegram.org/bots/api#getupdates). When switching between them, stop the previous receiver and agree on the webhook change separately; do not start a second poller for the same bot.

The receiver handles private messages and callbacks through the shared handler, sends the reply after commit and saves the next offset only after a successful send or a final refusal by Telegram. Ignored group/foreign messages are acknowledged with no reply. On a temporary failure the offset stays the same; the retry uses the same receipt and UUID. The reply's `retry_after` is respected, and a blocked chat does not stop other chats. After a timeout Telegram may already have accepted the reply: an external message is sometimes repeated, but the linking and the domain action are not. The offset and the lock are stored in the shared cache: a production environment uses the database cache. Clearing the cache may repeat the last update not yet acknowledged; saved receipts protect the action. Do not clear the production cache without need.

## Sign-in and linking

On the sign-in page choose "Sign in with Telegram", and in the profile "Link Telegram". The buttons are available once the bot is fully configured. Linking an existing account starts only from an active signed-in session.

1. The site creates a one-time attempt for 10 minutes and shows a six-digit code.
2. Open the bot by the link, compare the code with the original browser and confirm the attempt you started yourself.
3. Return to the same browser, refresh the status and check the Telegram name. If it does not match, press "Cancel".
4. Finish signing in or linking. Signing in from another browser or with another current account is forbidden.

The first sign-in creates an account with no password and no login, with the Telegram name, a neutral form of address, the current interface language, the BYN currency, a wallet and eight recovery codes. Save the codes on the profile page. A later sign-in uses the existing confirmed link and does not create a second account. An existing profile's name is kept; accounts are not merged by name. An identity already linked cannot be linked to another user, and an existing link cannot be replaced by this flow either.

An available photo is imported from Telegram's official server after the transaction completes. The path, a size of up to 1 MiB and the image format are checked; the photo is converted and stored in private storage, and `/profile/avatar` serves only your own avatar. On a failure or with no photo, the name's initial stays. A confirmed Telegram lets you sign in again when the password is forgotten; recovery codes for changing the password by login are described in [accounts](accounts.md).

## Quick actions

Notifications offer agreeing, declining, declining with "No money for groceries", separately accepting/declining a condition, "Done"/"Not done", "Paid" for a money duty, checking/disputing and five mood ratings: "Great", "Good", "Neutral", "Not great", "Bad". A complex condition is created in the app. Payment details and the request text never go into buttons.

For requests and duties the first press shows a question and "Confirm"/"Cancel" buttons. A mood rating is saved right away and offers an optional emoji; the emoji needs no confirmation either. A confirmation is valid for up to ten minutes; the original button for up to a day, but the deadline and the source state are checked again. The site's shared Actions, member permissions and record versions apply. Cancelling changes nothing. If the site has already changed the request or the duty, the old button does nothing. Repeating an update, a confirmation or a delivery does not credit a second reward or create a second duty.

The bot works only in a private chat: `from.id` must match `chat.id`, and the sender must not be a bot. The webhook requires a secret header and accepts at most 64 KiB. Only this exact route is exempt from browser CSRF; sign-in and linking forms stay protected. The callback acknowledgement is sent through `answerCallbackQuery` outside the transaction; its failure does not undo the domain action.

The bot's control reply is returned as a JSON `sendMessage` method in the webhook's HTTP response after commit. The encrypted reply is saved together with the receipt and the domain action: a repeated delivery of the update gets the same reply. Telegram does not return the result of executing such a reply; if the confirmation message did not appear, you can start a new attempt on the site. A control message may be repeated; the internal action cannot. The protocol follows the [official Bot API](https://core.telegram.org/bots/api#making-requests).

## Delivery

Messages about requests and duties hold the description, the state and the deadlines with no added links; payment details are not copied into a notification. The mood question has five buttons, the third being "Neutral". A message about a new rating shows it to the partner. A reminder passes the saved text. Link previews are off; sending is enabled explicitly.

Before every attempt the active couple, the recipient, the source being current, the deadline and the Telegram link are checked. A notification with a saved link is not moved to another account or chat. If there was no link yet, it can wait for one until it expires. Groups and channels are not used.

The outbox is a table of saved delivery intents. It holds no text, bot token or chat ID: only references to the source and the recipient, the state and technical metadata. A SHA-256 fingerprint is stored to verify the earlier chat. A queue job holds only two numeric IDs.

| Outbox state | Meaning |
| --- | --- |
| `pending` | Waiting for an attempt or for reprocessing |
| `processing` | The attempt is claimed by one worker for 90 seconds |
| `sent` | The Telegram API confirmed acceptance and returned a message ID |
| `failed` | The deadline or the attempts ran out, or Telegram rejected the request |
| `cancelled` | The source is stale, the couple ended or the link changed |

The network request runs outside the transaction. There are up to five attempts, with delays of 30, 120, 300 and 900 seconds between them, respecting a larger `retry_after` from Telegram. The last error ends the delivery. A blocked bot, wrong API credentials or a refused send end with no pointless retries. A missing link is checked again in five minutes and does not use API attempts. The overall lifetime of a regular notification is 24 hours, of a question three hours from its slot, and of a reminder up to and including a day after its time.

After a network timeout the message may have been accepted by Telegram. A retry can create a duplicate message; there is no guarantee of exactly-once external delivery. The internal action and hearts are not repeated. `sent` does not mean the user read the message. Transport errors are written without a URL with the token, the text or the full API response.

A repeated job does not claim an active attempt. After a worker crash the new attempt gets a different lease token; the old worker's result does not overwrite the new one. Running the dispatcher again does not create an extra job within the five-minute queueing window. An unexpected queueing error leaves the outbox entry for the next run.

## Settings and start

Neither production migrations nor sending processes were started automatically. Do the following only in a confirmed environment after the usual release checks, a backup and a restore check.

`.env` needs:

```dotenv
TELEGRAM_ENABLED=true
TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
TELEGRAM_WEBHOOK_SECRET=
QUEUE_CONNECTION=database
```

Fill `TELEGRAM_BOT_TOKEN` with the existing secret on the server without publishing it. `TELEGRAM_ENABLED=false` by default; having a token does not enable sending by itself. Point `APP_URL` at a real public HTTPS address: it is used in message links. The transport allows the durable database/redis/sqs/beanstalkd queues; synchronous and temporary drivers do not start sending. This configuration uses database.

Set `TELEGRAM_BOT_USERNAME` without `@`. `TELEGRAM_WEBHOOK_SECRET` needs a random secret of 32–256 characters from Latin letters, digits, `_` and `-`. Keep it only on the server. In the official `setWebhook` set the URL `APP_URL/telegram/webhook`, the same `secret_token` and `allowed_updates=["message","callback_query"]`. The address must be public HTTPS. Call `getWebhookInfo` and check the address and the absence of errors; do not publish responses with secret URLs. Setting up a live webhook is done by the owner in a confirmed environment, not by this development.

Migration `2026_10_10_104607_create_telegram_login_and_callback_tables.php` adds sign-in attempts, a log of processed updates, buttons and a registration lock. The new migration `2026_10_10_101637_create_notification_deliveries_table.php` creates the outbox. After the migrations are applied as agreed and the config is refreshed, start the separate processes:

```sh
php artisan queue:work database --queue=telegram,default --timeout=30 --tries=0
php artisan schedule:work
```

In an installed Sail project the equivalent commands are:

```sh
./vendor/bin/sail artisan queue:work database --queue=telegram,default --timeout=30 --tries=0
./vendor/bin/sail artisan schedule:work
```

On a server prefer Supervisor/systemd for the queue and a per-minute `schedule:run` through the system scheduler. Keep the worker timeout of 30 seconds below the queue's `retry_after` (90 by default). Queue limits: up to 20 messages per second per bot and up to one per recipient; the number of job retries is not limited because of waiting on these limits, while real API calls are limited by the outbox. After a code update restart long-lived workers with the usual release mechanism. For local Sail there is an optional `compose.workers.yaml` that uses the existing app image and network, with no new published ports. Once the environment is prepared as agreed, you can start and stop only these processes:

```sh
docker compose -f compose.yaml -f compose.workers.yaml up -d telegram-worker scheduler
docker compose -f compose.yaml -f compose.workers.yaml stop telegram-worker scheduler
```

They use the `.env` and the database of the current Compose project, so do not start them before the environment and the links are confirmed. If the image is not built yet, do the regular Sail install from the README first. For local hosting the processes are started by the [operations guide](operations.md). Do not start a second scheduler alongside an already running `schedule:work` or a system `schedule:run`.

## Schedule and missed runs

Three commands are registered every minute:

| Command | Work |
| --- | --- |
| `care:process-due` | Preparing reminders and the last valid mood question |
| `duties:check-overdue` | Recording overdue duties once and notifying both members |
| `telegram:dispatch-notifications` | Returning pending and lost attempts to the queue |

Questions are created for the times in the member's personal schedule (09:00, 15:00, 18:00 and 21:00 by default) in the couple's IANA zone. The computer's system time zone does not pick the slot. A personal schedule being disabled, the time of joining the couple and the three-hour window are taken into account. After a long downtime missed questions are not sent in a burst. A reminder exactly one day late is allowed; more than a day late it expires. A timely mark awaiting a check gets no waiting penalty; running again does not create a second penalty.

A cancelled reminder is not sent. While the send is being claimed it moves to `sending`; after that cancelling is unavailable. If the couple ended after the HTTP request started, the transfer already begun cannot be stopped; later attempts check the couple again. A joke sticker has not been chosen by the owner and is not sent.

## Schema and rollback

| Table | Data and protection |
| --- | --- |
| `telegram_login_attempts` | SHA-256 fingerprints of the link/browser/confirmation tokens, target user, expiry, verification code, status; the identity is encrypted and deleted after completion/cancellation |
| `telegram_auth_locks` | One technical row for sequential registration/linking, including Telegram IDs that do not exist yet; holds no personal data |
| `telegram_update_receipts` | Unique update ID, a fingerprint of the original body, the encrypted control reply; a changed body with the same ID is rejected |
| `telegram_callback_actions` | A fingerprint of the opaque token, account/couple/source/version/operation, an idempotency UUID, the stage and the expiry; no request text or payment details |

Attempt statuses: `pending → identified → confirmed → consumed`, with `cancelled` for cancellation. Button stages: `ready → awaiting`, with a separate confirmation `confirm → consumed/cancelled`. The sign-in service serializes registration with a shared short lock, then locks the user before the account; domain buttons use the order users by ID → couple → source. All data is re-read after waiting for a lock. HTTP and file processing stay outside transactions.

`telegram:prune-controls` daily deletes attempts and buttons seven days after expiry and receipts older than seven days. A deleted button had already expired, so deleting the receipt does not revive the action. This cleans technical confirmations, not the history of requests or hearts. Indexes on the expiry and the receipt time support the cleanup.

`notification_deliveries` references the couple and the recipient with restricting foreign keys. `kind` and `source_id` are an explicit source type with no polymorphic relation; `source_version` protects a reminder. Uniqueness of `(recipient_id, operation_key)` prevents repeating an event. The `(status, available_at)` and `(status, leased_until)` indexes support picking the next attempt and recovering after a crash. `queued_until` limits re-queueing, and `leased_until` with the hidden `lease_token` limits parallel sending. `telegram_account_id` keeps the ID of the earlier link without nulling it when the link is deleted.

To roll back, first stop the queue and the schedule and save a verified copy of the data. `down()` of the new migrations drops the control and delivery tables and loses their history; a backup is required to restore. Old code does not understand the reminder state `sending`: before returning to an old release, check unfinished sends and agree on a data plan — simply rolling the table back is not enough.

## Checks and diagnostics

`TelegramNotificationsTest` covers rollback, a real commit before queueing, the recipient, API 429/403, network failures, missed queueing, running out of attempts, cancellation, linking and lease recovery. `ScheduledCareTest` covers hours, day boundaries, clock changes, downtime, disabling, reminders and no penalty for a timely mark. `NotificationConcurrencyTest` covers two workers, cancellation at the same time as a send starting and two schedule scans in an isolated MariaDB. The check commands are described in [development](development.md).

`TelegramLinkCodesTest` covers the UUID, expiry, repeats, code replacement, permissions, hiding secrets, rollback and a full delivery through the queue. `TelegramPollingTest` covers the offset, handling after commit, temporary errors, the rate limit, ignoring groups and refusing an active webhook. `TelegramConcurrencyTest` additionally covers two contenders for one code and two codes for one user.

`TelegramAuthenticationTest` covers identity confirmation, the session, expiry, repeats, collisions and cancellation. `TelegramCallbacksTest` covers confirmation/cancellation, permissions, versions, mood and money marks with no rewards. `TelegramWebhookTest` covers CSRF, commit before the callback acknowledgement, rollback and cleanup. `TelegramProfilePhotoTest` covers private serving and the fallback. `TelegramConcurrencyTest` covers parallel sign-in, one identity in two attempts, a repeated update and the site at the same time as the bot, in a separate MariaDB.

If sign-in through the bot does not work, check the username, the secret, that it is enabled and that the attempt is valid. If there are no messages, first check that the transport is enabled, that there is a confirmed link, and the queue and schedule processes. Then look at the technical events `notification.*`, `telegram.transport_failed`, `schedule.*` and `failed_jobs`. Do not print `.env`, full request URLs or message texts. The send protocol and `retry_after` were verified against the [official Bot API](https://core.telegram.org/bots/api#sendmessage).

## See also

- [Mood and reminders](mood-and-reminders.md)
- [Duties and hearts](hearts-and-duties.md)
- [Development and checks](development.md)

## Meaningful notifications
Messages hold the request itself, its kind, the deadline, the agreed price/money amount, the answer, the counter-condition and its deadline, the result of rating an act or the duty description. The mood question and the partner's mood with an emoji arrive as text. The app adds no links to texts and no URL buttons to notifications; the bot's existing action buttons are kept. Personal payment details are not sent. A user's own link inside their description is not removed. Long texts are limited to the allowed message size.

A mood and its optional emoji are applied right on the press; requests and duties keep a separate confirmation. After a rating the bot asks you to write one emoji as a regular reply (ForceReply) or `/skip`. The `mood_emoji` and `mood_emoji_skip` operations use the `mood_emoji` source (the mood entry ID); for picking an emoji, `source_version` stores the index in the stable AddMoodEmoji::EMOJIS set. For buttons sent earlier the callback stays a random token with checks of the owner, the active couple and the entry being current. A repeated update returns the same saved reply; a repeated button does not create a second entry or notification. Mood confirmations issued earlier stay valid within their lifetime.

New emoji prompts store the mood entry's context in TelegramCallbackAction with stage=awaiting, source_kind=mood_emoji and a 24-hour lifetime. A reply is accepted only from the linked private Telegram, in an active couple and for the latest mood without an addition. The pending context is re-read under the lock; saving the emoji and the receipt is atomic. `/start` and the login callback keep their earlier routing. The bot does not save the raw reply text in the log. Old emoji keyboards work by the earlier callback protocol.

## Replies and stickers (0.21)

Actions in Telegram run right on the press; the extra "Confirm" button is no longer issued. The site's confirmations and the separate acceptance of a counter-condition are kept. Confirmations issued earlier are supported until they expire; the server still checks the member, version, source, deadline and repeats.

After choosing a mood, reply to the bot with one emoji or a sticker, including a TGS animation and a WEBM video. `/skip` skips the addition. A sticker with no active question gets an explanation, not an offer to sign in. A repeated update does not create a second download.

The download runs outside transactions as a separate encrypted job with five attempts. The Telegram path, the file size (2 MiB), the unpacked animation (4 MiB), the format and the image dimensions are limited. External resources and expressions are forbidden in animations. WEBP, the JSON from TGS and WEBM are saved in `storage/app/private/mood-stickers`; serving `mood.sticker` is allowed only to members of the matching couple, with no private cache. The `mood_entries.sticker_path`, `sticker_format` and `sticker_status` fields reflect loading, readiness or failure. A new mood, the couple ending and repeated replies do not let a late download replace the current answer. Tokens, the file and the raw payload are not written to logs.

## Notification format (0.25)

Notifications are sent with `parse_mode=HTML`: the first line is a bold heading with an icon and the name of the action's author, followed by the request or duty text and a short details line. Names and the texts of requests, conditions, comments and reminders are escaped in `MessageText`; the bot's replies to commands and presses stay plain text with no markup. If a message exceeds the limit, it is sent shortened and without emphasis, so that truncation does not break the markup.

In Russian the verb in the heading depends on the form of address in the profile: masculine, feminine or a neutral present tense; English uses one form. The name is used only in the nominative case, so a reminder reads "Anna reminds you" and a mood "Anna: good". Deadlines are written in words relative to the recipient's day in the couple's or the duty's time zone: "today, 18:00", "tomorrow, 09:00", "yesterday, 21:00", otherwise the weekday and the date. The "Request kind" line was removed.

Answer buttons come in pairs on one row: "Agree / Opt out", "Accept / Decline", "Done / Not yet", "I confirm / Dispute"; "No money for groceries" has its own row. The duty kind is added to the label only when one message has buttons for two duties. The mood is picked with one of five emoji buttons in a row, from bad to great. The digest of unfinished duties is split into "Overdue", "Today" and "Later". After a code update restart `telegram-worker`, `telegram-poller` and `scheduler`: they keep the earlier code in memory.

## Message language (0.32)

A notification is written in the recipient's language from `users.locale`, not in the language of whoever triggered the event: the text and the buttons are built in the queue worker under the addressee's language. The bot's replies to commands, presses and emoji use the linked account's language; an unlinked chat gets the default language from `APP_LOCALE`. To skip the emoji, `/skip`, `skip` and the Russian «пропустить» are accepted. The texts of requests, conditions and reminders stay as their author wrote them. After updating restart `telegram-worker` and `telegram-poller`.
