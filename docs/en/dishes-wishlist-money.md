[← Economy and catalogs](economy-and-catalogs.md) · [Back to README](../../README.md)

# Kitchen, wishes and money

Open these sections from the couple page. Your own dishes and wishes can be added without a couple too. A partner's catalogs are available only to members of an active couple. An administrator gets no access to other people's photos, wishes or payment details.

## Kitchen

A dish has a name, ingredients, a number of servings (1–20), a grocery cost estimate and a currency. The author chooses a regular or a special dish. A regular one is always free; a special one can be free or priced at 5–50 hearts. Suggestions: 10, 20, 30. The grocery cost does not create a money request and takes no money.

The catalog has "Mine" / "Partner's" tabs, kind filters and pagination. The "I want this dish" button opens a request with the card's current version. After sending, the name, ingredients, servings, estimate, currency, photo, kind and price in hearts are saved. Editing a dish does not change an order sent earlier. If the card was changed before the form was sent, refresh it.

With a negative free balance you cannot create or accept an order for a special dish, even a free one. Regular free food stays available. A paid dish uses the shared [hearts reserve](economy-and-catalogs.md): reserved after mutual agreement, deducted after the cooking is confirmed, released on an agreed cancellation. "No money for groceries" remains a refusal with no penalty.

## Wishlist and a gift as a condition

A wish is added manually: a name, an optional description and photo, a price estimate and a currency. There is no import from shops. In the answer to a regular request choose the "Gift from the wishlist" type, your wish, a description and a future deadline. The "Gift from the wishlist" catalog method is supported as well. The request author accepts the condition separately.

Offers keep a snapshot of the wish. A changed, archived or already promised wish cannot be accepted; a new offer of the current version is needed. Several offers before acceptance are allowed, but one wish holds only one accepted condition. After acceptance the card can be edited while the original snapshot of the promise is kept. Manually archiving a promised wish is forbidden.

The giver marks "Gift handed over". The recipient separately presses "Gift received". Only the second action saves the receipt date and archives the wish. A received wish cannot be promised again. Buying a gift earns no duty reward. Non-fulfilment and disputes follow the general deadline and check rules. On mutual cancellation, a disputed mark or the couple ending, a promise not yet received is released. For a new offer open the current card: its version changes together with the promise.

## Money requests and conditions

Open "Ask for money". Enter a positive amount up to and including 1,000,000, a currency, a future deadline and an optional purpose and category; confirm sending. Save payment details in your profile first. Currencies: BYN, RUB, USD, EUR; the couple's currency is used by default. Money amounts are passed as strings, stored with two decimal places and checked through BigDecimal with no float. More than two fractional digits, negative amounts and scientific notation are rejected. A dish or wish estimate may be zero; a money request may not. There is no conversion and there are no fees.

The recipient of the request can agree or decline. Counter-conditions are closed for such a money request. The "I paid" mark belongs to the payer; "Money received" to the recipient of the money. Until the second confirmation the duty stays awaiting a check. Repeated submissions, marks and confirmations do not create a second payment or reward. The app makes no transfer and checks no bank.

In the answer to a regular request you can also offer a money condition: an exact amount, a currency, a description, a deadline and your own payment details. The performer of the original request receives the money, and the author pays the condition. The original action and the money condition stay separate duties; the reward relates only to the checked action.

Payment details are saved as an encrypted snapshot on acceptance. Before acceptance the other member does not see them. Changing the profile does not change an accepted agreement. Payment details are hidden from model serialization and shown only to the members of the accepted request; after the couple ends the history is available to former members but not to new partners.

## The kitchen screen

Switch between "Mine" / "Partner's" and the "All" / "Regular" / "Special" filter. A card tells the grocery estimate from the order price and shows the ingredients, servings and the available action. A negative balance blocks special dishes even at 0 hearts. A paid dish needs a sufficient free amount, with the already accepted reserve taken into account. These hints do not replace the check on sending.

A new card uses the current couple's currency, or the profile's chosen currency before a couple exists; the currency can be changed manually. Existing cards are not converted. With JavaScript the editor automatically keeps a regular dish free and shows a preview of the chosen photo; a wrong format, size or an unreadable image needs another file. Without JavaScript the server validates the form. If a photo in the catalog fails to load, the card shows "Photo unavailable"; the name and prices stay visible.

## The wishes screen

The "Mine" and "Partner's" tabs show different available actions: editing, adding and the archive are available only to the owner. The "Wish" / "Archive" switch keeps the catalog owner. The photo, name, optional description and exact price with currency fit on the card; long text wraps on a narrow screen.

A free wish can be chosen in the condition panel of an incoming request. Your own wish's card leads to the request list; the link itself creates no promise. "Gift promised" means an accepted condition, and archiving is not available yet. "Gift received" appears in the archive after the recipient's separate confirmation. The system buys nothing and transfers no money.

Press "Add a wish" and fill in the name, an approximate price and a currency. The description and photo are optional. With JavaScript the chosen photo is visible before saving; the server checks the file again. Archiving sits under an expandable item and needs an explicit confirmation. Without JavaScript the same fields and actions are available; no preview is shown.

## Photos and access

JPEG, PNG and WebP up to 4 MB and 4096×4096 go through a server check and are re-encoded to a JPEG of at most 1200 pixels on the long side. Metadata and original names are not kept. SVG and other formats are rejected. A photo is optional: the card shows the "No photo added" caption.

Files live on the `catalog_photos` disk inside `storage/app/private/catalog-photos`, with no public link or automatic URL. The `photos.*` routes check the owner, the current partner or participation in the specific historical request. Responses are not cached. When a photo is replaced, the previous file is kept for history snapshots; no automatic cleanup of old files runs. A backup must include the private photos, the database and the earlier encryption key.

## Schema and applying

Migration `2026_10_09_234525_create_product_catalog_and_money_tables.php` creates `dishes`, `wishlist_items` and `money_requests`; it adds dish snapshots to `partner_requests` and amounts/gift snapshots/encrypted payment details to `request_conditions`. The link of an accepted promise is stored in `wishlist_items.claimed_condition_id`; receipt and archive are separate dates. A money request has no duplicate bank statuses: completion is determined by a separate `Duty`.

Creation keys are unique per owner; a version protects editing. Catalog changes lock the owner, while accepting a condition and confirmations use the shared lock order of the couple's members. Acceptance, the promise, the reserve and creating duties are atomic. Photos are processed outside the domain transaction; an unused file from a repeated or failed write is deleted.

A production database is not changed automatically. After an agreed backup apply migrations the usual way:

```sh
php artisan migrate --no-interaction
```

The new catalogs are user data and need no seeding. `DishSeeder`, `WishlistItemSeeder` and `MoneyRequestSeeder` create only demo data when called explicitly in local/testing; they are not part of the general seeder. Do not run them on a production database. The migration's reverse method removes the new links and tables but destroys this block's data: restoring needs a backup, not another `up()`.

## Checks

Pest covers exact amounts, versions and snapshots, private images, member permissions, a negative balance, the dish reserve, repeated submission, the two stages of payment/gift and no reward for financial fulfilment. An isolated MariaDB covers competing dish edits, two promises of one wish, repeated money requests and simultaneous receipt confirmations. PHP GD is required; it is already in the local PHP and explicitly enabled in CI.

Check in a browser: add a dish → the partner asks for it → agreement → cooking → check; create a wish → offer a gift → accept → hand over → receive; ask for money → agree → pay → confirm receipt. Forms of important actions need an explicit confirmation and work without JavaScript. On a version error refresh the page. Photos and personal texts do not get into diagnostic messages.

## See also

- [Requests and conditions](requests.md)
- [Duties and hearts](hearts-and-duties.md)
- [Development and checks](development.md)

## Picking movies together (0.22)
A personal movie has a private JPEG photo (JPG/PNG/WEBP input up to 4 MiB), a title and a validated HTTP(S) link. The link is stored encrypted; the app does not download it. The photo is available to the owner and the current partner.
movie_choices fixes up to 100 unique movies from both lists at the start of a pick. Matching title/year are merged. Swipes open after first_ready/second_ready of both members; a new round has its own votes, and old MovieVote records do not affect it. The first mutual like saves selected_movie_id and finishes both screens; later clicks do not change the choice. No matches after all votes means Exhausted; opting out before the start means Declined. The roulette uses the same shared list, the server saves one random result and both see it; another spin does not replay. All transitions are serialized by locking the users/couple/session.
The old POST movies/draw returns 410 with a link to the new pick; the old votes endpoint accepts a vote only in an active confirmed session.
