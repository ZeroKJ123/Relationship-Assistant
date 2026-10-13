[← Couples](couples.md) · [Back to README](../../README.md) · [Duties and hearts →](hearts-and-duties.md)

# Requests and counter-conditions

From the couple page open "Our requests". In an active couple either partner can send a request to the other. Enter the kind, a description and a required future deadline in the couple's time zone. Currently available are general text requests for a service, cooking, guests, a shared event, money and a gift. Starter templates and categories are already available. [Dishes, wishes and exact money requests](dishes-wishlist-money.md) are connected as separate flows. Old text requests stay in the history; create new money amounts through "Ask for money". [Friends, guest visits and dates](friends-guests-dates.md) have their own forms on the couple page.

The recipient explicitly confirms agreement, refusal or a counter-condition. A condition has its own required future deadline and a type: an action, kisses, a custom condition, money or a wishlist gift. The request author separately accepts the current version or declines the request together with the condition. Agreeing to a request and a condition creates separate duties but does not mean completion: a reward appears only after the performer marks it done and the partner checks it. See [duties and hearts](hearts-and-duties.md).

## The condition panel

On the request card press "With a condition". Pick the kind with a button, refine the method if needed, and fill in the description, the required future deadline and the confirmation. Sushi needs a size; money needs an exact amount and currency; a gift needs a current wish of your own. When the kind changes, values that no longer apply are cleared and not sent. Real kisses have no virtual wallet. Hearts remain the agreed price of the original service; a separate free-form condition does not deduct them.

The panel scrolls, closes with a button or Escape and returns focus. On a server refusal it opens with the entered data and a message. Without JavaScript the form stays on the page; incompatible fields must be left empty. With a stale version, read the new state and confirm your decision again. Nothing is resubmitted automatically.

For food the refusal reason is chosen with a separate "No money for groceries — no deduction" button. It is not shown for other requests. Creating a request offers direct links to guests, an event and exact money amounts; a template also leads to the matching form.

## States and versions

| State | Available action |
| --- | --- |
| Awaiting an answer | The recipient agrees, declines or proposes a condition |
| Awaiting acceptance of the condition | The author accepts/declines; the recipient can propose a new version |
| Agreed | Answers and accepted conditions are readable |
| Request declined | The history is readable |
| Cancelled | Unfinished duties are closed and the reserve is released |

A new version of a condition replaces the previous one for acceptance and keeps it in the history. Confirming a stale version is rejected. Agreed data cannot be changed by one side. Agreeing or accepting a condition again does not create a new transition. Creation is protected by a unique submission key: repeating the same form returns the original request, and changing the content under a repeated key is rejected.

An overdue request or an overdue condition cannot be accepted. Declining stays available for a request still awaiting an answer. The author declining a counter-condition closes the request with no penalty for the recipient. After the couple ends, new operations are closed and the history stays available to former members. Other users, including an administrator and a new partner, get no access.

## The refusal rate

On sending, the amount and version of the refusal rate are saved. The initial version is set in `config/economy.php`: version 1, rate 15. A regular request stores 0. A new version saved by an administrator affects new requests and keeps earlier snapshots. After the first initialization the config no longer changes the rate. See [economy and catalogs](economy-and-catalogs.md).

"No money for groceries" is a structured refusal reason for a cooking request. Its penalty is always 0, including the internal "Really want" flow; no bank balance is checked. The reason is not available for other request kinds.

The "Really want" flag is available in the form and checked by the server: it needs an active weekly agreement, a non-negative free balance of the author with the reserve taken into account, and a free slot in their weekly quota by the couple's zone. Sending uses the slot, and a refusal does not give it back. Repeating the submission does not use the quota again. The recipient's refusal penalty is applied through the shared ledger in one transaction with the decision, exactly once; `decline_penalty` keeps a snapshot of the amount. The "No money for groceries" exception creates no deduction.

## Price, catalog and cancellation

Pick a template and press "Apply the template", then refine the description and the deadline. The expense category is optional. The template and category names are saved as a snapshot. A service and a selected special dish can have a price in hearts; regular food is free. The price is not transferred to the performer and is deducted only after the result is checked.

A counter-condition method can be picked from shared or your own ones: an action, real kisses, sushi, money, a wishlist gift or a custom option. Sushi requires choosing a small, medium or large set. The description and the size are saved in the accepted version; the catalog creates no obligations by itself.

Before acceptance the author can cancel the request. After acceptance one member suggests cancelling and the other confirms; until then the request and the reserve stay in force. A confirmed duty cannot be cancelled. On final cancellation unfinished duties become `cancelled` and the reserve is released; penalties already applied are not refunded. Decisions are recorded in request events with the version and the member.

## Implementation and checks

The main commands are in `app/Actions/Requests/` and the routes in `routes/requests.php`. FormRequests turn validated input into DTOs. Policies and Actions check the member, the couple, the state and the version on the server. All transitions and their events are written in one transaction. Locks follow the order users by ID → couple → request → conditions. After waiting for a lock, the state is re-read with a locking read for MariaDB REPEATABLE READ.

List history is paginated and member names are eager-loaded. Forms have linked labels and an explicit confirmation, and they work without JavaScript. With JavaScript on, the pressed button is kept while repeated submission is blocked. Logs hold only technical IDs, versions, statuses and refusal reasons, without the request or condition text.

SQLite tests cover deadlines, permissions, state, versions, repeated operations, safe text output and the rate staying unchanged. Separate MariaDB tests use independent processes for repeated submission, competing agreement/refusal, acceptance while a condition is being replaced, and repeated acceptance of a condition. See [development checks](development.md).

## Migration and rollback

Migration `2026_10_09_222256_create_partner_request_tables.php` adds `partner_requests`, `request_conditions` and `request_events`. It does not change earlier records and has not been applied to a production database automatically. `down()` drops all three tables together with requests, conditions and the event history. Applying or rolling back in a production environment needs a verified backup and a restore procedure. Rolling back the schema does not restore deleted data.

See also [couples](couples.md), [interface](interface.md), [CodeStyle](../../CodeStyle.md).

## See also

- [Economy and catalogs](economy-and-catalogs.md)
- [Development and checks](development.md)
- [Interface](interface.md)

## Asking to rate an act
In a new request choose "Rate an act", describe an act already done and set an answer deadline. The partner gives a rating of 1–5, optionally adds a comment, or declines. Only the recipient saves the rating, and only before the deadline. Repeating the submission does not create a new answer; a finished rating cannot be cancelled or replaced. This request is free, with no "Really want", penalties, rewards or separate duties. `partner_requests` gained a nullable `rating` and an encrypted `rating_comment`; the `rated` answer goes into the existing event queue.

## The short request form
By default a regular service is created, and "Rate an act" is chosen explicitly. In view are the kind, the text and the deadline (tomorrow in the couple's time zone by default; it can be changed). Templates and extra settings expand separately. A price is available only for a service or a selected dish; when the kind is switched, a price that no longer fits is cleared. Guests, dates and money use separate forms.

The recipient sees "Agree", "Agree with a condition" and a collapsed refusal. The condition expands with a standard button, with no dependency on JavaScript; its deadline defaults to the request deadline. The author sees the new condition on the home screen, opens the request and separately presses "I accept the condition". Confirmations, the current version and server checks are kept. Catalog settings, the condition history and cancellation are collapsed. Overdue requests do not offer unavailable answers.

## Completion stages and actions in a request (0.23)
The status between the members and the deadline reflects actual completion: created/awaiting an answer, accepting the condition, in progress, awaiting confirmation, done. With a counter-condition, completion needs confirmation of all linked duties. Refusal, cancellation and disputed completion are highlighted in red; waiting in yellow; confirmed completion in green. Colour is always backed by text.

In a request you can mark completion and check it through the same Actions and permissions as in the duties tab. After sending, the user returns to the request. Action confirmation and the version check are kept. Cancellation sits next to completion; before agreement the author does it, after agreement both partners do. Completed duties are not cancelled. The `partner_requests.created_by_admin_id` field marks the origin of a system test request; it is clearly labelled on the site and in the first Telegram notification, then goes through the couple's usual cycle.
