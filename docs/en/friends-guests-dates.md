[← Kitchen, wishes and money](dishes-wishlist-money.md) · [Back to README](../../README.md) · [Mood and reminders →](mood-and-reminders.md)

# Friends, guests and dates

An active couple's page offers "My friends", inviting guests and inviting to a date or an activity. Regular invitations are free and available with a negative heart balance.

## Personal friends

A friend only needs a name. The list belongs to the user: a partner and an administrator cannot read or change someone else's entries. A name can be changed, and an entry can be archived after confirmation. Archived friends are not available for new visits.

Editing checks the entry version. If the name changed after the form was opened, refresh the page. Sending the same form again does not create a second friend.

## Guest visit

Choose from one to twenty of your own active friends, a future start date and time, a duration from 15 to 1440 minutes and a reason. When you move between pages of the friend list, the selected guests and form fields are kept; paging does not create a request and does not put the reason into the page address.

On sending, the guests' IDs, versions and names, the start, end, duration and reason are saved. The partner sees only the guests of that specific visit. Renaming or archiving a friend does not change the history. If a friend changed before sending, the whole invitation is rejected: update the selection and send again.

The deadline of the shared request is the end of the visit. A counter-condition is agreed and fulfilled separately: for example, you can choose sushi and a set size. Agreeing creates a duty but does not confirm that it was done.

## Date and activity

Enter the invitation kind, a title and a future date with a time. An extra description and a dress code are optional. The deadline of the shared request is the start of the event; the recipient must answer before it. The time is entered in the couple's time zone, stored in UTC and shown in the couple's zone. A local time that does not exist because of a clock change is rejected.

The recipient explicitly confirms agreement, refusal or a counter-condition. A wishlist gift, money and sushi follow the existing condition rules. The author accepts a proposed condition separately; accepting an invitation does not confirm that a gift was received or a condition fulfilled.

After sending, the guests and the time cannot be changed by one side. To reschedule, cancel the earlier request and create a new one. Before acceptance the author cancels; after acceptance both members are needed. After the couple ends, former members can read the history but cannot create invitations. A meeting place and a separate calendar are not implemented yet.

## Implementation and checks

Routes live in `routes/social.php`. Validated input is turned into a DTO; thin controllers pass it to Actions. The shared request engine provides versions, condition acceptance, completion and cancellation. `SocialInvitations` checks that the attached invitation matches the request's type, description and deadline, locks the selected friends and saves the snapshot in the shared transaction. Repeating the same submission returns the original invitation; changing the data under the same key is rejected.

Logs hold technical IDs, the number of guests, the event type and refusal codes. Friends' names, reasons, descriptions and dress codes are not written to them.

`SocialInvitationsTest` covers personal access, the archive, versions, repeated submissions, snapshots, selection across pages, time and duration, a negative balance, conditions, cancellation and an ended couple. `SocialConcurrencyTest` covers sending a visit and a date at the same time, competing changes to a friend, and archiving while sending. These MariaDB checks run only in an isolated test database; see [development](development.md).

## Migration and rollback

Migration `2026_10_10_072832_create_social_invitations_tables.php` creates `friends`, `guest_visits` and `date_invitations`. Friends have an owner, versions, an archive and a creation key unique within the owner. A guest visit and an invitation have a unique link to a request; foreign keys forbid deleting linked records. The guest list is stored as a JSON snapshot.

A production database has not been changed automatically. Applying it in a confirmed environment needs a verified backup and a restore check. Then apply migrations with the usual `php artisan migrate --force` in that environment and check creating a friend, a visit and an event from both accounts of the couple.

`down()` drops the new tables together with friends and invitation details; shared requests stay, so a rollback loses structured data. Restoring needs a backup; rolling back the schema does not bring the data back. `FriendSeeder`, `GuestVisitSeeder` and `DateInvitationSeeder` create only demo data in local/testing, are not part of `DatabaseSeeder` and are not needed for a release.

## See also

- [Requests and conditions](requests.md)
- [Couples and invitations](couples.md)
- [Duties and hearts](hearts-and-duties.md)
- [CodeStyle](../../CodeStyle.md)
