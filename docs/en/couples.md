[← Accounts](accounts.md) · [Back to README](../../README.md)

# Couples and invitations

After signing in, open "My couples" and invite your partner. The start date is required and cannot be in the future in the couple's time zone. Europe/Minsk is used by default; IANA zones are accepted. Days together are counted by calendar dates of that zone, and the counter stops once the couple ends.

A link is valid for 7 days and is shown to its author once after it is issued. A new link cancels the previous one. The author can also cancel a link separately. Cancelling and expiry leave a pending couple: you can issue an invitation again or end the couple to accept someone else's invitation.

The recipient first signs in or registers, then explicitly confirms acceptance. After registration the profile shows the recovery codes and a "Continue accepting the invitation" link. You cannot accept your own link, join as a third member or have several pending/active couples at once. Accepting again as the same recipient does not create a new record.

Both members are equal. Either one can end the couple after a separate confirmation. This frees both for a new couple and cancels pending invitations. The history stays available to the former members, including after a new couple is created. It is not available to a new partner or to an outside administrator. The `manage-system-settings` permission is meant only for administrators and does not open personal data.

## The weekly "Really want" limit

On the couple card open "Our agreements". Each member answers separately how many "Really want" requests per week seem right to them: from 1 to 14. Both see the answers. After the second answer the average is suggested, rounded up; for example, 3 and 4 give a suggestion of 4. Even identical answers do not activate the rule automatically.

Each member explicitly confirms the same version. Until both agree, `WeeklyLimit::getActiveLimit()` returns null and "Really want" requests are closed. The limit is the same for both and is counted separately per author; the week starts on Monday in the couple's IANA zone. Sending a "Really want" request checks the quota and a non-negative balance, and a refusal does not give the slot back. More in [duties and hearts](hearts-and-duties.md).

You can decline the proposal under discussion or suggest another number. A new proposal needs two new consents, including the author's. Changing a personal answer suggests a new average; an unchanged answer does not create an extra version. A request with an old version asks you to refresh the page. Confirming again does not add a second consent from the same person. An agreed rule cannot be cancelled by one side: while a new one is discussed, the previous one stays in force until two consents replace it.

Pending and ended couples cannot change agreements. The version history is available to former members after the couple ends, but not to new partners. While a couple is ended, its historical limit does not allow new requests.

## Data structure

| Table | Main fields and constraints |
| --- | --- |
| `couples` | `status`: pending/active/ended; `started_on`, IANA `timezone`, `currency`, `ended_at` |
| `couple_members` | Historical `user_id`, `couple_id`, `slot`: first/second, `joined_at`, `ended_at`; couple+member and couple+slot are unique |
| `couple_members.active_user_id` | A unique nullable reference. While the couple is pending or active it equals user_id; it is cleared when the couple ends, and the historical user_id is kept |
| `couple_invitations` | Author/couple, unique SHA-256 `token_hash`, pending/accepted/cancelled, expiry, recipient and acceptance date |
| `couple_agreements` | One record per couple, the current proposal version, a nullable active version and its weekly limit |
| `agreement_answers` | Each member's number; agreement+user is unique |
| `agreement_proposals` | Version/limit/optional author/acceptance date; statuses proposed, accepted, declined, superseded; agreement+version is unique |
| `agreement_approvals` | Confirmations of a specific version; proposal+user is unique |

The invitations table does not hold the original token. A random link has 64 characters; the temporary display goes through an encrypted flash session bound to the couple. Responses are not cached; Referrer-Policy: no-referrer limits leaking the link through the Referer header. Logs hold technical IDs and the operation stage, without the link or personal text.

Operations run through Actions inside transactions. Users are locked first in ascending ID order, then the couple and the invitation. Checks are repeated under the lock. Database constraints enforce one current couple per user and two slots per couple. If the members changed while ending was being confirmed, the request asks you to refresh the page and confirm the current members again.

All agreement changes are made by Actions in transactions. Locks follow the order users → couple → agreement → proposal/answers/approvals. Locking reads are used for state that could have changed while waiting: a plain read in MariaDB REPEATABLE READ may see an old snapshot. Confirmations of an old version are not carried over. Logs show technical IDs, versions and the fact of activation; personal answers are not written.

## Migration and rollback

Migration `2026_10_09_193842_create_couple_tables.php` adds three new tables without changing account data. It has not been applied to a production database automatically. Before applying it, follow the usual release procedure with a verified backup. `down()` drops these tables: the schema goes back, but the history and invitations are lost. Keeping the data needs a backup and a restore check before the rollback.

Migration `2026_10_09_203000_create_couple_agreement_tables.php` adds four agreement tables without changing earlier data. It has not been applied to a production database either. Its rollback deletes answers, versions and confirmations: they can be kept only with a backup prepared and verified in advance. A rollback does not restore lost history.

## Checks

Regular Pest tests use in-memory SQLite. Separate integration tests run in a throwaway MariaDB 11.4: two different guests trying to accept one link, one recipient with two links, and repeated acceptance by one recipient. They use independent PHP processes with a shared start barrier. The database's own constraints are checked too. For agreements the tests cover simultaneous answers, two confirmations of one version, a repeated confirmation by one member, competing counter-proposals, confirming an old proposal while it is being replaced, and the final consent arriving while a member leaves the couple. GitHub Actions runs both groups.

For running and protecting the test database see [development](development.md). Shared [requests and conditions](requests.md) are already available; Telegram, hearts and completion checks remain the next stages of the AF plan.

## Relationship dates
On an active couple's page you can change the relationship start and add a wedding date. The wedding is optional: an empty field means the date is not set. Both dates are no later than today in the couple's time zone; the wedding is no earlier than the relationship start. Next to the number of days, the home screen and the couple page show the calendar length in years and months. For an ended couple the length is fixed at the moment it ended. The `couples.married_on` (nullable date) and `dates_version` fields protect against being overwritten by a stale form. Both members of an active couple can edit the dates.
