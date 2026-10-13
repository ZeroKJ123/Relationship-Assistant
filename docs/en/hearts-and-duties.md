[← Requests](requests.md) · [Back to README](../../README.md) · [Economy and catalogs →](economy-and-catalogs.md)

# Duties and hearts

Agreeing to a new request creates a duty: the performer marks the result with an explicit confirmation, then the partner separately confirms completion or disputes the mark. An accepted counter-condition creates a second duty with the roles reversed and its own deadline. "Our duties" is reachable from the couple card; "My hearts" from the duties and the couple card.

## The duties screen and confirmation

"Our duties" opens on the "Active" tab: duties to be done and overdue ones. "Under review" holds marks waiting for the partner. "Completed" holds confirmed, disputed and cancelled duties; the exact status is on the card. "All" shows the whole list. Tabs are kept across pages, with 10 duties per page and the nearest deadline first.

A card shows the performer, the checker, the deadline and the possible reward for an unfinished duty. Open a duty and choose an answer. With JavaScript a bottom panel appears: check the content, tick the confirmation and press "Yes, send the answer". "Back" and Escape close the panel and return focus. Sending is locked against a repeated click. Without JavaScript the confirmation and the buttons are available in a regular form.

After the mark the performer waits for a separate check by the partner. When disputing, the penalty from the accepted agreement is shown. The "Not done yet" answer does not extend the deadline. Stale versions and changed states are checked by the server; the error is shown on the page. Payment and a gift use the neutral labels "Payment sent" / "Money received" and "Gift handed over" / "Gift received".

## Personal ledger

"Hearts and history" is reachable from the profile. The screen shows the balance, free hearts and the reserve, then the history at 20 operations per page. A manual operation is available only in an active couple: choose thanks or a deduction, enter the number and a reason, then confirm. The amount field does not prefill an arbitrary value; limits are checked on the server by the rules in force.

## Completion and deadlines

| State | Meaning |
| --- | --- |
| To be done (`pending`) | The performer can answer "Done" or "Not done yet" |
| Awaiting a check (`reported`) | The mark is saved; the checking partner can answer |
| Completion confirmed (`confirmed`) | The final result; the reward is applied once |
| Mark disputed (`disputed`) | A final dispute; the false-report penalty is applied |
| Cancelled (`cancelled`) | Mutual cancellation of an accepted request closed the unfinished duty |
| Overdue (`overdue`) | The overdue penalty is applied; the performer can still mark completion |

`reported_at` and `reviewed_at` are set by the server separately. Dates are stored in UTC and shown in the duty's fixed zone. A timely mark gets no overdue penalty for waiting on the check, even if the partner disputed it after the deadline. A late mark gets the overdue penalty that has already come due, once; disputing such a mark can separately add the false-result penalty. Checking your own duty, a stale version and another couple are rejected. Repeating the same answer does not create a second reward or penalty.

After acceptance there is no one-sided change of the deadline or the content. New actions are closed after the couple ends; the history is available to former members. No automatic obligations are created for requests accepted under the previous version: the new migration does not assign completion or financial operations to historical agreements.

## Initial economy rules

The initial rates are in `config/economy.php` and are saved as the first version on initial seeding or first use. Later changes are made by an administrator through an editor with a version audit; see [economy and catalogs](economy-and-catalogs.md). The refusal rate is fixed when a request is sent, and the completion reward and penalties when it is accepted. A new administrative version does not rewrite these snapshots or the ledger; after initialization the config no longer changes the rules. The limits in force are checked at the moment of the operation.

| Rule | Initial value |
| --- | --- |
| Starting hearts | +20 once per account |
| Confirmed duty | Up to +5; at most +15 per day and +75 per week |
| Thanks for the partner | At most +25 per day and +50 per week |
| Manual deduction by the partner | At most −10 per day and −25 per week |
| Positive cap | 100; a credit may be partial or zero |
| "Really want" refusal | −15 by the snapshot at sending |
| Overdue completion | −10 once |
| False report | −15 once |

The reward goes to the performer of a service or a counter-action; a money payment and buying a gift earn nothing by themselves. When a limit is used up, the result is saved with a zero reward and does not lead to a later credit. Limits count actual amounts by the operation date, not by the planned deadline. The day and the week (from Monday) are determined by the duty's zone for rewards and by the current couple's zone for manual operations. The accounting is personal and is not reset when the couple changes.

"Really want" is available in the form after two consents to the weekly rule and with a non-negative free balance of the author, the reserve taken into account. The quota is separate for each author: a sent request uses a slot, a refusal does not give it back, and a repeated submission does not use another one. With a negative balance regular free requests, including cooking, stay available. The "No money for groceries" reason waives the refusal penalty and checks no bank account. A service price is reserved after agreement, deducted after the check and released on cancellation or a dispute; see the [reserve rules](economy-and-catalogs.md). Money and gift settlements will be implemented separately.

A manual operation needs a reason and an explicit confirmation. The recipient is determined by the server from the active couple; you cannot pass your own or someone else's wallet. When a daily/weekly limit is exceeded, the operation is rejected as a whole; a partial credit applies only at the positive cap. The balance can go negative from penalties and allowed deductions.

## Ledger and access

The wallet is personal; the history is shown only to its owner, including after the couple changes. Starting hearts are created at registration, and for an older account on first access to the wallet. A new partner and an administrator get no access to someone else's ledger. Reasons of manual operations are encrypted in the database and never reach the logs.

`Ledger` atomically changes the balance and adds an entry: a unique `operation_key`, the type, the requested and actual integer amounts, the balance after the operation, and technical references to the members/couple/duty. Repeating a key with changed data is rejected. Updating and deleting entries are forbidden by the application model; this does not block privileged direct SQL. Access to the database and backups must keep this boundary. Before an operation the last entry is verified, and the full check verifies the ledger sum; a mismatch raises an error and is never fixed silently.

A domain transaction locks users in ID order, the couple and the specific data. The ledger locks the owner and the wallet; limits are read with a locking read so as not to count a stale MariaDB snapshot. Lists and the history are paginated, and related names are eager-loaded. Logs hold technical IDs, statuses and amounts, without personal descriptions or reasons.

## Background checks

`duties:check-overdue` processes only duties awaiting completion in active couples, in batches of 100. The schedule runs the command every minute with overlap protection. An environment with migrations applied needs the Laravel scheduler process (`php artisan schedule:work` in development or a system `schedule:run` once a minute). In this stage no permanent process was started automatically.

`php artisan hearts:check-balances` checks existing wallets against the ledger with no automatic fixing. Notifications and Telegram delivery are connected in later stages.

## Schema and rollback

Migration `2026_10_09_223847_create_duty_tables.php` adds `duties` and `duty_confirmations`: the performer/checker, the source request and condition, deadlines, states, versions, rate snapshots and the answer history. The unique pairs "request + duty kind" and "duty + version" protect against duplicates. Indexes support the couple's lists and the overdue query.

Migration `2026_10_09_224135_create_heart_tables.php` adds `heart_wallets` and `heart_transactions`: one wallet per user, a unique operation key, signed integer balance/amounts and references that forbid deleting dependent data. Indexes support weekly budgets and a wallet's latest entries. All new models have factories with consistent relations and balances.

Both migrations were checked only in isolated test databases; they were not applied to a production database. Using the new screens requires applying the migrations separately in an agreed environment. Roll back in reverse order: hearts first, then duties. `down()` drops the tables and their data and does not restore the history. Applying or rolling back in production needs a verified backup, a saved encryption key and a restore procedure.

SQLite checks transitions, permissions, repeats, amounts, limits, deadlines, rate snapshots and transaction rollback. MariaDB checks parallel marks, contradicting checks, the last quota slot, the last available reward/thanks limit, simultaneous balance changes and the starting bonus. See [development](development.md), [requests](requests.md), [couples](couples.md), [interface](interface.md) and [CodeStyle](../../CodeStyle.md).

## See also

- [Economy and catalogs](economy-and-catalogs.md)
- [Development and checks](development.md)
- [Interface](interface.md)

## Reminders and the chore draw (0.22)
The care:process-due scheduler prepares Telegram reminders at 10/15/20 in the couple's time zone. Pending/Overdue/Disputed obligations are included, for the performer only; Reported/Confirmed/Cancelled and requests not yet accepted are excluded. An unread/unlinked delivery lives for 30 minutes after the slot; there is no night catch-up. The list is re-read before sending, so a duty already done is excluded. duty_reminder_batches with a unique couple/user/scheduled_at and a couple lock prevent repeats.
"Who will do it?" creates chore_draws: the text and the deadline are immutable, with two separate consents on the site. After the second consent the server picks one of the members and creates, through the shared CreatePartnerRequest/RespondToRequest, a free accepted service and an obligation. The performer/request are saved in one transaction with the consents; a repeat does not replay, and opting out before the result assigns nothing. Rewards/checks/deadlines keep working as for regular obligations. A draw creates a new duty; obligations already agreed are not reassigned.

## Reconciling a manually chosen test balance
The regular `hearts:check-balances` checks that the balance matches the ledger and refuses to work on a mismatch. It does not fix data automatically. By an explicit decision of the test balance's owner, `ReconcileRecordedBalance` can keep the current value and add the missing difference as a separate `balance_reconciliation` operation ("Test balance reconciliation"). This is an administrative record of previously chosen test values, not a partner's thanks or a reward. Web forms and Telegram do not offer this operation type.

Only the configured admin panel owner has access to the Action; a manual run happens in a confirmed environment with a backup. It takes the expected current balance, the expected ledger sum, an operation UUID and a reason. Under the user/wallet lock both values, the last entry and the presence of the starting operation are checked. An inconsistent ledger or changed values require a new check; the Action does not rewrite old entries. The encrypted reason, the administrator ID and an `admin_audits` record are added in one transaction; the balance itself does not change. Repeating the same UUID/parameters returns the earlier entry even after a later regular operation. If the values already match, no new entry appears. After reconciliation the standard `hearts:check-balances` is run.

The scenarios are covered by `HeartBalanceReconciliationTest` and `HeartConcurrencyTest`: keeping a positive/reduced test value, the history staying unchanged, repeats, rollback, permissions and refusal on stale data. No schema changes or new migrations are needed.

## Changing the balance in the admin panel (0.24)

"Balance" in the private panel lets you find a user by name/login, see the current balance and add or take away from 1 to 1000 hearts. Each form needs a reason and a confirmation. If the wallet is not opened yet, the first successful operation credits the regular starting bonus. The resulting test balance is limited to −1000…1000; an administrative credit may exceed the usual reward cap. A negative balance without reserves is allowed by the general product rules.

The new `admin_adjustment` type writes the actual amount, the remainder, the administrator and the encrypted reason to the existing `heart_transactions` ledger; the history is not rewritten. The change and `admin_audits` are saved in one transaction under the user/wallet lock. Repeating one UUID returns the earlier result; changing the recipient, amount or reason under the same UUID is rejected. The owner's active unlocked session is also checked when entering the ledger operation.

The reserve of already accepted services cannot be deducted through the panel. Parallel deductions re-check the reserve remainder under the lock. Credits and deductions keep the standard ledger reconciliation possible. No changes to the hearts schema are needed.
