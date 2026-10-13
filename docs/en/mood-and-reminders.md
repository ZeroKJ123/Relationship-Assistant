[← Friends, guests and dates](friends-guests-dates.md) · [Back to README](../../README.md) · [Telegram and scheduling →](telegram-and-scheduling.md)

# Mood and reminders

From the couple page open "Mood and history" or "Reminders". Both sections are available to the couple's members, including users with a negative heart balance. Ratings and reminders neither credit nor deduct hearts.

## Mood

Choose "Great", "Good", "Neutral", "Not great" or "Bad", then save. In Telegram the same five options come in this order: "Neutral" is the third button. The partner sees the mood on the home screen and the couple page, with the date in the couple's time zone. A new rating creates a separate entry; the previous one stays in the history. Repeating the same submission does not create a second entry, and changing the answer under the same key is rejected.

Both members see the whole saved history of their couple, with no age limit on entries. An earlier couple's history does not become available to a new partner. An administrator gets no right to read other people's ratings.

The personal schedule by default asks at 09:00, 15:00, 18:00 and 21:00 in the couple's zone; since version 0.26 a member sets from one to eight times with minute precision in "My schedule" (the `couple_members.mood_schedule_times` column; an empty value means the default times). It can be disabled after confirmation; the partner's setting does not change. Saving manually works with the schedule disabled too. Once [Telegram and scheduling](telegram-and-scheduling.md) are set up, the questions arrive in the linked private chat. After a missed run only the last question that is still valid is created; questions from before joining the couple are not created.

The shared save command supports Web/Telegram and answering a mood prompt. A prompt is valid for three hours from its scheduled time and is accepted only from its owner and only once. An expired prompt, or one that precedes a newer manual rating, does not change the mood. A scheduled prompt also does not work while the schedule is disabled. Disabling the schedule closes scheduled prompts opened earlier; enabling it does not revive them.

## One-off reminder

Press "Remind your partner", enter the text and a future time in the couple's zone, then confirm sending the form. The recipient is determined by the server — it is the couple's other member. The time is stored in UTC. A local time that does not exist because of a clock change is rejected.

Reminders do not repeat and are not edited. To change one, cancel the earlier entry and create a new one. Before sending starts the author can cancel; the recipient and outsiders cannot. Cancelling needs a confirmation and the entry's current version. Repeating creation or cancellation creates no extra actions.

| State | Meaning |
| --- | --- |
| Scheduled | Saved; the time has not been processed yet |
| Awaiting delivery | The time has come and the entry is prepared; delivery is not confirmed yet |
| Cancelled | The author cancelled, or the couple ended before processing |
| Overdue | Processing was more than a day late |
| Being sent to Telegram | The external send has started; cancelling is no longer possible |
| Sent to Telegram | Telegram confirmed accepting the message; this is not a read receipt |
| Delivery not confirmed | The send was rejected or attempts ran out; after a network failure the result may be unknown |

The `care:process-due` command prepares one-off reminders through `PrepareReminder`, and a separate queue sends them to Telegram. Preparation verifies the version and the state, skips a cancelled entry and checks the active couple and the delay. Exactly one day is allowed; more than a day is not. A prepared entry can be cancelled before sending starts. The sender re-checks the cancellation, version, couple, deadline and the earlier Telegram link, then atomically records the start of the transfer. After that an external request already in flight cannot be stopped.

After the couple ends, new ratings and reminders are closed. The history stays with the former members; the author can cancel a reminder whose sending has not started. Processing a scheduled entry of an ended couple moves it to the cancelled state.

## Implementation and checks

Routes live in `routes/care.php`. FormRequests turn input into typed data, and controllers pass it to Actions. A mood entry stores the author, couple, rating, source, time and submission key. Locks follow users by ID → couple → entry; the state is re-read under the lock. A reminder has immutable text, recipient and time, and transitions are protected by versions.

Logs hold technical IDs, sources, versions and states. Mood ratings and reminder text are not written. The home screen checks that the session is current after a password change. The history is paginated; the latest ratings are selected with a separate limited query with eager-loaded names.

`MoodAndRemindersTest` covers permissions, repeats, the history, stale prompts, the personal schedule, time, cancellation and expiry. `CareConcurrencyTest` covers saving a mood simultaneously, two answers to one prompt, creating a reminder, repeated processing and processing at the same time as cancellation. See [development](development.md) for commands and test isolation.

For a manual check sign in as both members of a test couple: save a mood as the first and check it on the second's home screen; disable the personal schedule and save a new rating manually. Create a reminder for the future, check the right recipient and time, then cancel it as the author. A cancelled entry must keep its text and show "Cancelled" with no button to cancel again.

## Migration and rollback

Migration `2026_10_10_095220_create_mood_and_reminder_tables.php` adds `mood_prompts`, `mood_entries`, `partner_reminders` and the personal schedule fields in `couple_members`. An enabled schedule and version 0 are the initial values. Unique keys protect repeated creation of a rating/reminder, the mood prompt slot and its answer. Indexes support the couple's history and selecting entries by state and deadline. Foreign keys forbid deleting linked members and the couple.

The migration has not been applied to a production database. Applying it in a confirmed environment needs a verified backup and a restore check. Apply `php artisan migrate --force` in that environment and run the manual scenario above. Saving moods and reminders by itself does not need Telegram. External delivery needs the settings and processes from [Telegram and scheduling](telegram-and-scheduling.md).

`down()` drops the new tables and schedule settings and loses ratings, prompts and reminders. Rolling back the schema does not restore data — a backup is required. Demo seeders are available only in local/testing, are not part of `DatabaseSeeder` and are not needed for a release.

## See also

- [Couples and invitations](couples.md)
- [Development and checks](development.md)
- [CodeStyle](../../CodeStyle.md)

## Mood emoji
After a mood is saved the site offers an optional emoji from a set. You can pick an emoji or press "Skip". Only your own latest entry in the active couple can be supplemented, once; repeating the same submission is safe. `mood_entries.emoji` is a nullable string. A separate `mood_emoji` event tells the partner about the addition through the existing queue, so a rating already sent does not lose the new emoji. If the initial rating is still waiting to be sent, its notification is replaced by the addition so that two identical messages do not arrive. The home screen shows only the partner's mood with the emoji; the history section still shows both.

## Ask about the mood now
The "Ask about your partner's mood" button sends the other member a separate prompt for three hours, even if they disabled the personal schedule. It is a request to record a mood: the partner picks the rating themselves. Within a minute of creation, pressing again reuses the same prompt with no duplicate notification. After a minute the button creates a new prompt with a new notification and closes the earlier manual prompt: its old buttons no longer accept an answer. After an answer or a new rating made on their own, the mood can also be asked again. The message on the site tells a new prompt from a quick repeat; creating a prompt does not mean actual delivery to Telegram. Two new prompts in one second are rejected. The recipient sees the prompt in the mood section without a link from Telegram; answering works both on the site and with the bot's buttons. Disabling the schedule does not close manual prompts.

Migration `2026_10_10_180619_add_manual_flag_to_mood_prompts.php` adds a boolean `mood_prompts.is_manual`, initially `false` for earlier prompts; new manual entries have `true`. The rollback removes only the flag and keeps the entries, but they start following the schedule. A backup is needed before applying and before rolling back. `ManualMoodPromptTest` covers the recipient, repeats, a disabled schedule, the site and the shared Telegram Action, expiry, a new rating and couple permissions.

In Telegram a mood rating is saved right away with no separate confirmation. The bot asks you to reply with one emoji of your own (including a compound one, with a skin tone, or a flag). `/skip` or "Skip" saves the mood without an addition. The reply goes through the shared AddMoodEmoji operation and the owner and latest-entry checks. Plain text and several emoji are rejected with a request to reply again. On the home screen the partner's mood and emoji are highlighted and the ask button is compact; the "Record mood" link was removed.

## History and notifications (0.22)
The mood history is kept with no deletion deadline. A short click on the home card asks about the mood; a 600 ms hold opens the partner's calendar, also available with Shift+Enter or H on the button. Picking a date shows the average for the couple's local day, the number of entries and details with time/emoji/sticker; the list is split by 50 entries. For the average, "Bad"/"Not great" = 1, "Neutral" = 2, "Good"/"Great" = 3; old values are kept. Days with no entries do not get an invented zero.
A mood rating creates one notification. Adding an emoji/sticker updates the entry and the live screen, with no new outbox. If the first notification has not been sent yet, it carries the current emoji; one already sent is not duplicated. Old pending mood_emoji deliveries are cancelled by the source check.

## Asking yourself through Telegram (0.23)
The partner's mood history has "Record your own mood". `POST couples/{couple}/mood/self` creates a manual question for the user themselves, even with the schedule off. It can be answered in Telegram or in the site's mood section. A saved rating notifies the partner; the existing emoji/sticker addition works with no second notification.

`mood_prompts.is_self_requested` distinguishes the text of a self-requested question. Repeating before the one-minute cooldown reuses the current question; a new send replaces the stale one. The couple, recipient, deadline and repeated-answer checks stay shared.
