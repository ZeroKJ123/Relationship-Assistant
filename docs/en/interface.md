[← Development](development.md) · [Back to README](../../README.md) · [Accounts →](accounts.md)

# Interface basics

The `/` page uses a lilac, yellow and pink palette. A guest sees an introduction to the app; a signed-in user sees the home screen with the data of their account and current couple. Names, the balance, the mood and dates are never replaced by demo values.

## Components

| Component | Purpose |
| --- | --- |
| `card` | Cards with the shared palette |
| `button` | Primary, quiet and dangerous action |
| `field` | Linked label, field and error |
| `avatar` | A photo or a safe first letter of the name |
| `status` | Text status |
| `dialog` | A modal shown as a bottom sheet |
| `confirmation` | Confirming a form submission without a repeated click |
| `tabs` | Accessible tabs |
| `bottom-navigation` | Home, the current couple's requests and the profile |
| `dish-card` | Photo, ingredients, groceries, hearts and the available action |
| `condition-form` | The shared content of the counter-condition form |

Components live in `resources/views/components` and the shared layout in `resources/views/layouts/app.blade.php`. CSS defines shared tokens, touch target sizes and a visible focus. The local Nunito with Cyrillic ships under the SIL Open Font License in `public/fonts/OFL.txt`.

`resources/js/modules/dialogs.js` opens/closes sheets, returns focus and supports the arrows, Home and End in tabs. `confirmation` requires an existing form by ID, checks its validity and blocks a repeated press; the server still has to check permissions and idempotency. Without JavaScript the main content is available, and text is shown instead of the modal hint.

## Manual check

After `npm run build` open the site at the address from the README.

1. At a width of 320–390 px make sure the text and the bottom navigation fit with no horizontal scrolling.
2. Press "How it will work": the sheet opens, Tab stays inside it, and Escape closes it and returns focus to the button.
3. In tabs use the arrows, Home and End: the selected tab and its content must match.
4. Turn on reduced motion: transitions are disabled.
5. Turn JavaScript off: the registration information stays available.

Registration, sign-in and the profile are already available; couples and invitations work too; general text requests and conditions are available. Sign-in and confirmed actions in Telegram are implemented; a live connection needs a configured bot and a public HTTPS address. No bank transfers are made and there is no integration with a live Home Assistant.

## Home screen

The home screen shows the user's name, the partner, the length of the relationship in calendar days by the couple's zone, the latest mood ratings, the total balance, the reserve and free hearts. Four tiles lead to the partner's kitchen, a new request, a date and the personal wishlist. With no couple, creating one or inviting is offered; personal catalogs are available right away. Until the first limit is agreed, a link to the agreement is shown.

"My duties" shows up to three of the user's nearest unfinished obligations, including ones awaiting a check and disputed ones. "All" opens the full list. A separate card shows the latest incoming request awaiting an answer. An earlier couple's data never reaches the home screen. A negative amount is shown with its sign, and regular free food stays available.

`DashboardController` uses `DashboardData` and the typed `DashboardViewModel`. `WalletSnapshot` reads the balance and the reserve consistently under a lock. On a loading error a 503 page is returned with a retry button and a safe reference number. Diagnostics hold only technical IDs, the loading time and the number of duties.

Check 360/390/430 px, long names, no partner, an unagreed limit, empty duties, new requests and a negative balance. The bottom navigation leads to real pages. JavaScript shows the transition state and clears it when you go back.

## Couple screens

"My couples" shows the current couple and the paginated history. An invitation needs the recipient's confirmation, and ending a couple needs a separate confirmation screen. Forms work without JavaScript; JavaScript blocks a repeated press while sending. Statuses come with text. The profile has links to couples and to continuing to accept a link after registration.

Check long names, an empty history, the link on a narrow screen, cancelled/expired invitations and keyboard confirmations. More in [couples and invitations](couples.md).

## Agreements

The limit screen shows both partners' answers, the suggested compromise, the number of consents and the paginated version history. Each proposal is accepted with an explicit confirmation; you can decline or suggest another number. The personal answer and new proposal fields have different IDs and linked labels. The rule in force stays visible while a replacement is discussed. For a pending/ended couple the screen is read-only.

## Requests and conditions

The list, creation and details are reachable from the couple page. The deadline is entered in the couple's time zone; the condition form shows a separate deadline. The recipient answers with an explicit confirmation, and the author separately accepts/declines the current version of the condition. The history is kept. New operations are closed after the couple ends.

Check forms with and without JavaScript, wrapping of long names at 320 px, linked labels, returned errors and repeated submission. The locking script saves the name and value of the pressed button in a hidden field before disabling the buttons, so that an agreement/refusal is not lost. After going back the temporary fields are removed and the buttons are available again. Details: [requests and conditions](requests.md).

The "With a condition" button opens a bottom sheet with a choice of an action, kisses, a custom option, money, a gift or sushi. Fields that do not apply are hidden and not sent; changing the kind clears the earlier amount, wish and size. The currency is kept as a preference and sent only for money. The author still accepts the condition separately. Escape closes the sheet and returns focus; the content scrolls inside it. On a server error the sheet opens with the earlier input. Without JavaScript a regular form and all labels are shown; choose compatible fields manually.

## Duties and hearts

The couple card gives access to "Our duties" and "My hearts". A service and an accepted counter-condition have separate cards, performers and deadlines. The performer explicitly confirms the mark, and the partner separately checks the result; both server dates and the answer history are shown. The personal ledger shows the actual amount, the reason of a manual operation and the balance after it. The thanks/deduction form needs a reason and a confirmation; the recipient is determined by the server.

Check the sequence of both members, the ban on checking your own duty, waiting for the result, a mobile width of 320 px and long names. Forms work without JavaScript. Repeated submission does not duplicate a credit. More in [duties and hearts](hearts-and-duties.md).

## Economy, reserve and catalogs

The administrator opens "Hearts economy" from the profile, changes the rules and explicitly confirms saving. The history shows versions, the UTC time and the administrator's technical number. A regular member has no access to the editor.

"My hearts" shows the total balance, the free part and the reserve. Check creating a paid service → agreement → mark → check, and also suggesting cancellation → the second member agreeing → releasing the reserve. The author of a cancellation suggestion sees that the partner is awaited.

"Catalogs" are reachable from the couple. A template is applied with a separate GET form and works without JavaScript; after picking, refine the deadline. A personal method is seen by its owner, and the sent snapshot of a condition by the partner. Sushi needs a size, which does not determine the price.

The new pages were checked at a width of 320 px with a long name, with no horizontal scrolling and no duplicate IDs; templates and saving the rules were checked without JavaScript. See [economy and catalogs](economy-and-catalogs.md).

## Kitchen and editor

The kitchen shows your own dishes or the current partner's catalog, kind filters and two different prices: the approximate grocery cost in the card's currency and the order cost in hearts. With a negative balance special dishes are unavailable, including free special ones; when the free remainder is short for a paid dish, the reason is shown. The server checks the rules again on sending, so a stale page gives no right to order.

When a regular dish is chosen, the editor sets 0 hearts. A photo can be previewed before saving; an unsuitable format, size or a read error is shown next to the field. The server check and private serving are kept. An unavailable image on a card is replaced with a caption. Details: [kitchen, wishes and money](dishes-wishlist-money.md).

## Obligations and the wishlist

Duties are split into active, awaiting a check, completed and all. Confirming an answer opens in a separate bottom sheet; the checking partner goes through their own stage. A possible reward is not shown as a new promise for a duty already completed. More in [duties and hearts](hearts-and-duties.md).

A wish card puts the photo on the left and the details on the right, and uses one column at widths up to 360 px. A shared photo module serves the kitchen and wishes: the preview, file errors and unavailable photos in the catalog. The owner can edit and archive a free wish; the partner views it. Adding, viewing and the archive work without JavaScript. More in [kitchen, wishes and money](dishes-wishlist-money.md).

## Extra forms and settings

The profile is split into personal data, currency, payment details and the Telegram status. "My space" gives access to the kitchen, wishlist, friends, hearts, couples and the current couple's schedule. The economy is visible only to the administrator. The connection status is shown as text; the bot's secret data is never shown.

The money, date and reminder forms use shared currency and multi-line text fields and show errors next to the fields along with the couple's time zone. Regular fields are kept on an error; payment details are not carried into temporary input, and the profile shows the previously saved data again. Sending is locked against a repeated click. A confirmation is required without JavaScript too. A money request makes no bank transfer, and a reminder is not considered delivered just because it was saved.

The hearts ledger shows the personal balance, the free remainder, the reserve and the history. A manual thanks or deduction needs an amount, a reason and a confirmation. The economy editor is split into balance, rewards, thanks, deductions and penalties; the version history stays available. All extra screens use the bottom navigation and mobile typography.

## Installing on a phone and being offline

The "The app on your phone" block was removed from the profile by the owner's decision of 12 October 2026; the `pwa-help` component is kept and not connected. Its instructions: Safari on iPhone/iPad → "Share" → "Add to Home Screen"; Chrome on Android → the install or add-to-home-screen menu. If the browser offers installing, a separate button appears; the prompt opens only after a press. Declining does not count as installing. In the installed app the hint is hidden, and the top padding respects the safe-area.

When the network is lost, an already open page shows a warning and blocks sending regular forms until the connection is back. If the network dropped while sending, check the result after reconnecting: the server may have accepted the request while the response did not arrive. There is no automatic queue or resending of actions.

Navigating to a page while offline shows the public "No connection" screen with status 503. It holds no personal requests, photos, payment details, balances or sign-in information. The service worker saves only versioned public icons, the offline screen and hashed build files; it does not intercept POST/PUT/PATCH/DELETE, personal JSON, photos or other origins. Responses with private/no-store, redirects, a wrong file type and errors are not saved. The cache is limited to 50 build files, and the app's old caches are deleted on update.

A successful sign-out returns the server-rendered guest page. When the browser restores a saved signed-in page, its content is hidden and requested again so that the server re-checks the session. The PWA creates no long-lived storage of personal data. Updating the worker does not reload a filled-in form.

Local Chromium, a width of 390 px, turning the network off/on and automated checks of the cache, forms and privacy were verified. Real Safari on iOS and Chrome on Android, installing on a device and public HTTPS have not been verified yet; these acceptance checks stay open. Install requirements and browser differences: [MDN](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable).

## See also

- [Development and checks](development.md)
- [CodeStyle](../../CodeStyle.md)
- [Running the project](../../README.md)

## Update 0.21

The home screen uses a raster background with clouds and hearts and a single illustration set; proportions are kept. The user's own photo is shown as a round avatar in the bottom menu. Files: `public/images/clouds-hearts-v1.png`, `public/images/love-icons-v1.png`. They were made with the built-in imagegen: the background is a light lilac sky with soft white clouds, pink hearts and yellow sparks; the transparent 3×3 set holds a chef's hat, a speech bubble, a heart, a gift, a party popper, a car, a star, a house and a bell.

The mood card shows a bold short rating, the emoji/sticker and the entry time. Pressing the whole card asks the partner; the toast closes after two seconds. The home screen does not offer recording your own mood separately. Cars are shown only if the current user added one; adding is in the detailed profile settings. The shared time zone is changed there too, with a confirmation. The "My couples" buttons, the separate money button and the bottom "Our couple" in the request list were removed. In creation all kinds are available under "What the request is about"; templates and the extra bottom links are hidden. Template snapshots saved earlier stay in the history.

Open pages receive WebSocket signals and refresh their content. Drafts, the selected tab and open settings are kept; an open confirmation is refreshed after it is closed.

In 0.22 illustrations are shown as separate SVG viewports over the earlier raster atlas: exact frames exclude neighbouring pictures, and preserveAspectRatio xMidYMid meet centres each one. The raster originals are unchanged. The home screen shows the partner's photo, highlighted request text, a personal compliment and invitations to a shared pick/draw. Holding the mood card opens the calendar; a short press saves a mood prompt.

## Shared wheel spin (0.23)
The movie and chore roulettes need two consents and two open visible pages. The client marks presence every 1.5 seconds; a mark lives for 6 seconds in the shared server cache. A hidden tab does not extend presence. Both consents without presence do not pick a result. WebSocket refreshes the page when the state changes, and the presence check also gets the result if a signal was missed.

Under a lock the server saves one result and `spin_started_at` with a 5-second delay. Both browsers use the shared start time and a 3-second animation; the answer is shown after the spin. Opening later shows the same finished wheel with no new pick. If a page disappears right after its last heartbeat, presence may last up to 6 seconds; the result does not change after the start. With JavaScript off the automatic shared start does not happen.

A cache store shared between HTTP processes is required (for example a configured database/Redis), not `array`. Concurrent actions do not create a second obligation or a new result.

## Shared themes, icons and the home screen builder (0.24)

The private admin panel `/admin-panel` got a side menu: overview/tests, balance, themes, icons, the home screen builder and the economy. Access to the new sections needs the panel owner and a valid second password; the administrator role alone does not replace it. Appearance settings are shared by everyone, including the guest page.

"Themes" offers clouds and hearts, a spring garden, summer sun, autumn and winter. You can add up to 30 themes, change the main colours, text, cards, two gradient colours and the angle, decorations, or upload a PNG/JPEG/WebP up to 4 MB and 4096×4096. Uploaded appearance is publicly available: use images you can share with every visitor. "Theme switching" sets a manual choice or the seasonal mode: March–May, June–August, September–November and December–February. The season boundary is determined by Minsk time when a page is opened/refreshed, with no extra scheduler. Any saved theme can be picked for each season.

A theme's icons are set with symbols; the "Icons" section replaces the shared illustrations with symbols or pictures. Priority: global replacement → the theme's symbol → the earlier illustration. Going back to "From theme / regular" removes the global replacement. Replacements apply in quick actions and the bottom menu.

The builder manages the ready-made home screen blocks and eight quick actions. You can drag by the handle or move with the arrows, turn elements off/on, change labels and the card colour, pick 1–4 columns (at most two on a phone) and add text cards, up to 20 blocks in total. Conditions: always, has/has no partner, more than zero cars/no car, positive balance, has duties/a new request. Links and permissions stay on the server; arbitrary scripts, HTML and condition expressions are not executed. Service invitations and mood questions stay visible outside the editable layout.

The preview uses the current account's data and has a width switch and a sample of conditions; clicking a block opens its settings. It performs no actions and shows a placeholder for a block with no data. Settings apply to everyone only after confirming and saving. A competing save of a stale version is rejected with a suggestion to refresh the page.

Migration `2026_10_12_122633_create_appearance_settings_table.php` adds a single `appearance_settings` record: a primary ID, a revision number, the JSON of themes/icons/layout and dates. Saving locks the record, checks the revision and atomically adds `admin_audits`. Appearance is stored on a separate `appearance_assets` disk in `storage/app/appearance`; include the directory in backups and keep it between releases. New files are served through the restricted `appearance-assets` route, and personal photos on other disks are not reachable through it. Before applying in a confirmed environment save the database and the files, then run the standard migrations and build by the [operations guide](operations.md). The migration runs in a production database only after the environment is confirmed. Rolling the new table back deletes the saved appearance, so save it together with the files before a rollback.

## The "Pumpkin latte" theme and lists (0.25)

The default theme is "Pumpkin latte": a cream background, a pumpkin accent and the "Leaves" decoration. Leaves are drawn with CSS masks and take the theme's primary colour; the decoration is available to any theme. Migration `2026_10_12_135203_add_latte_theme_to_appearance_settings.php` adds the theme to the saved settings, makes it current and, in the seasonal mode, assigns it to autumn; custom themes are unchanged. The rollback removes the theme and brings back "Clouds and hearts" and the season's autumn theme; edits to "Pumpkin latte" itself are lost on rollback. Earlier themes stay in "Themes" and work in the new layout.

Illustrations from the raster set were replaced with line icons of the `icon` component; `illustration` still applies the global replacement first, then the theme's symbol. The "Who will do it?" tile got its own icon. The font stays Nunito.

The main screens are built from rows with thin dividers instead of cards: `row` (a list row), `group-label` (a group heading with a counter), `facts` ("field — value" pairs), `steps` (stages), `stat-row` (a summary) and `settings-section` (an expandable section). `card` stays for separate blocks and forms. On inner pages the header shows a back link: to the home screen by default, and a screen can set the `back` and `back-label` sections.

- Home: the greeting, the partner row, mood and hearts in two columns, quick actions as rows, duties as a list.
- Requests: the groups "Awaiting an answer", "In progress" and "Completed" within a pagination page; the "Answer" label is visible only to the person whose answer is awaited (`RequestProgress`). An accepted request opens the duty card right away — first the duty where the viewer's answer is needed; a request with no duties opens its own page with the answer and conditions.
- New request: the topic is picked with toggle buttons (regular radios without JavaScript); the quick deadlines "Today", "Tomorrow" and "In a week" are computed on the server in the couple's time zone and appear only with JavaScript working; the submit button is pinned above the bottom menu.
- Duties: the groups "Overdue", "Today", "Next 7 days" and "Later", then by status (`DutyGroups`); day boundaries follow the duty's time zone. The duty card shows the stages and the details as a list. Under the heading it says whose request or condition it is and who carries it out. Before the performer's mark the checker sees a disabled confirm button with an explanation; after the mark the performer sees a disabled mark and that a check is awaited. Cancelling the request (the shared `request-cancellation` component), and the amount and payment details of a money duty are available right on the card; after cancelling from the card the user returns to it. The "To the request and conditions", "All duties" and "My hearts" links were removed.
- Hearts: a large balance, the thanks/deduction form in an expandable section (open on an error) and the history with a signed amount.
- Kitchen and wishlist: underlined tabs, and dishes as rows with a photo, a price and an action.
- Mood: the rating is picked with an icon; the current month's calendar shows each day's latest personal rating and a summary: the most frequent rating and the number of recorded and hard days (`MoodCalendar`). The partner tab shows the same calendar of their ratings: days are links to the selected day's entries with the average and a chart, and arrows page through months. The mood card on the home screen: a press asks the partner, and a hold opens the partner's calendar; the hint is available to screen readers.
- Profile and couple page: links as a list, forms in expandable sections; all forms work without JavaScript.

Check 360/390/430 px, long duty and dish names, empty lists, earlier themes and JavaScript turned off.

## Name and icon (0.25.2)

In Russian the app is called «Любимость»: the name is in the header, page titles, the manifest, `apple-mobile-web-app-title`, the offline screen and `APP_NAME`. Since 0.32 the English interface shows "Relationship Assistant" in the header and page titles. "Our couple" stayed the name of the couple page and of links to it. The 0.25.2 icon was a glossy pumpkin heart with leaves on a dark background (`love-v2-*`), drawn as a vector in the same work and flattened to PNG; the earlier `heart-v1-*` files were removed.

The service worker's public set got version `v2`: `offline-v2.html`, new icons and the `our-couple-public-v2` cache; the earlier cache is deleted on activation. The cache prefix was kept so that an update finds and removes old copies. A previously installed app may keep the old icon and name until it is reinstalled — that is how phones behave.

## The request page in the duty layout (0.25.3)

The request page mirrors the duty card: the request kind and status in the header, the request text as the heading, a line about the viewer's role, the "created → answer → completion" scale (`RequestProgress::getStep`; a declined or cancelled request highlights no steps), details as "field — value" pairs, explanations as hint blocks and cancellation as the shared `request-cancellation` section. The page stays separate because a request that is not accepted, declined, cancelled or a rating request has no duty: this is where people answer, propose and accept a condition. An accepted request from the list still opens the duty card right away.

## Quick actions and your own schedule (0.26)

The home screen's quick actions gained "Remind" (the couple's new reminder form) and "Compliments" (the `profile/compliments` page); both are visible when there is a partner and are configured in the builder, which now has ten actions. Migration `2026_10_12_145211_add_reminder_and_compliment_tiles_to_appearance_settings.php` appends them to the saved layout without changing the rest; the rollback removes them together with their label and colour. The compliment editor was removed from the profile and saves on its own page.

In "My schedule" a member sets the times of mood questions: time fields with an "Add another time" button; an empty field removes a question, duplicates are merged and the order is ascending. At least one and at most eight options are needed; enabling and disabling the schedule has its own button. Without JavaScript one extra field is available per save. The `couple_members.mood_schedule_times` column was added by migration `2026_10_12_145210_add_mood_schedule_times_to_couple_members.php`; its rollback returns everyone to the default times. One script (`data-repeat-editor`) handles adding fields in compliments and the schedule.

In 0.26 the mood card sent the prompt to the partner 700 ms after a single press, and a second press within 1.5 s sent the question to yourself. This double press was removed in 0.29 (see below); the press state was kept in the module by the form's address rather than in its element, because a live refresh replaces the card after any database change.

## Personal theme (0.27)

The profile's "Theme" section offers themes from `AppearanceCatalog::PERSONAL_THEMES`: "Pumpkin latte", "White", "Love" and "Floral". The choice is stored in `users.theme` and applies only to that user; an empty value or a theme that no longer exists in the settings means the shared theme from the admin panel (`AppearanceStore::currentId`). Guest pages always use the shared theme. A theme changes colours, the background and the decoration (`leaves`, `hearts` and `flowers` are drawn with CSS masks; the white theme has no decorations); the home screen layout, line icons and blocks do not change.

Migrations: `add_theme_to_users` adds the column (the rollback returns everyone to the shared theme), and `add_personal_themes_to_appearance_settings` appends three new themes to the saved settings without touching existing ones; its rollback removes these themes together with their edits. The new themes can be edited in the admin panel like the others; the decoration list gained "Hearts".

The partner's compliment is shown in the home screen greeting instead of the fixed phrase and still changes every five minutes; with no compliments the phrase "Care starts with small things" stays. The "Compliment" layout block is kept in the builder for the sake of earlier settings but shows nothing; with the greeting block turned off the compliment is not visible.

## Reward shop and memorable dates (0.28)

**Reward shop** (`/rewards`, the "Rewards" quick action, a link in the profile). Each member has a personal reward list: a name, optional details and a price from 1 heart (the `rewards` table, an archive instead of deletion, and a version and operation key as in the other personal catalogs). The partner sees the other's shop and a "Buy" button; when free hearts are short the button is disabled with an explanation. Buying opens the new request form with the kind fixed to "Service" and the price fixed, and creates a regular paid request: agreement, the reserve, the check and the deduction follow the earlier rules of paid services, and there is no separate deduction at purchase. On creation the server verifies that the reward belongs to the request's recipient, is not archived and that the price matches the current one (`RewardOffers`); otherwise the request is not created. Archiving a reward does not touch requests already created. Migration `add_rewards_tile_to_appearance_settings` appends the quick action to the saved layout.

**Memorable dates** (`/couples/{couple}/memorable-dates`, a row under the partner on the home screen, a section on the couple page). The list is built by `MemorableDates`: the relationship and wedding anniversaries are computed from the couple's dates, and the rest are stored in `memorable_dates` (up to 50 per couple, yearly or one-off). The next occurrence is computed in the couple's time zone; 29 February in a non-leap year is marked on the 28th; past one-off dates are hidden. Both active members can add and delete, and deleting needs a confirmation. The `care:process-due` scheduler, at 09:00–09:30 in the couple's time, creates a reminder for each member three days before and on the day itself (`CreateDateNotices`; the `memorable_date_notices` log rules out repeats) and puts it into the shared Telegram queue; the text is escaped, and for the couple's dates the anniversary number is added.

Rolling back `create_rewards_table` deletes the shops (requests and hearts stay), and rolling back `create_memorable_dates_tables` deletes the added dates and the reminder log. After updating restart `telegram-worker`, `telegram-poller` and `scheduler`.

## Streak and date wheel (0.29)

**Streak** (`/couples/{couple}/streak`, a row under the partner on the home screen, a section on the couple page). `CoupleStreak` counts consecutive days, in the couple's time zone, on which both active members recorded their mood or the day was restored. While today is not closed, the streak is counted up to yesterday and is not broken; the status shows who is awaited. The flame level depends on the length: a spark from 1 day, a little flame from 3, a flame from 7, a bonfire from 30 and a star flame from 100 (`CoupleStreak::LEVEL_DAYS`); the `flame` component changes colour and glow by level. The calculation looks at the last 800 days.

A broken streak can be restored if the gap adjoins the current streak, there was a streak before it and the missed days do not exceed the remaining restores; all missed days are restored at once, each using one restore. A calendar month in the couple's time gives five restores per couple (`streak_restores`; one day is restored once). The action needs a confirmation and is available to both active members. Rolling back the `create_streak_restores_table` migration deletes restored days, and streaks that relied on them break.

**Date wheel** (`/couples/{couple}/date-ideas`, a link in the date form and on the couple page). A couple stores up to 12 ideas (`date_ideas`). With two or more ideas the wheel is available: the server picks an idea at random, and the page shows a three-second spin of the existing wheel component and then the result; with animation off the result is visible right away. The pick is kept only in the flash session and is not synced between partners — it is a personal helper, unlike the shared movie and chore wheels. "Invite to this date" opens the date form with the title filled in; another couple's idea is never filled in. Rolling back `create_date_ideas_table` deletes the ideas; sent invitations stay.

The chore draw wheel shows the partners' names four times around — eight alternating sectors instead of two halves. The server computes the stopping sector (`ChoreDrawController`): it always belongs to the chosen performer and is the same for both partners; how the performer is chosen did not change.

The chore draw result is announced full-screen: after the wheel stops, the `spin-announcement` modal opens with the performer's name, the chore, confetti and the "Open the duty" and "Back to the wheel" buttons; Escape and the button return to the page. The modal is shown only to those who waited for the spin on the page; when opened later, without JavaScript or after closing, the result is visible as a line under the wheel. With reduced motion no confetti is shown.

The double press on the mood card was removed by the owner's decision of 12 October 2026: a live page refresh replaces the card between presses, and the gesture was unreliable. A press on the card again asks the partner right away, and a hold opens the history. A question to yourself is sent with the "Send a request for my mood" button on the "Mine" tab; it took the place of the rating form, so your own mood is no longer recorded on the site — only by answering the question in Telegram. The `mood.store` route and adding an emoji remain for compatibility, with no links in the interface.

A wish photo in the wishlist opens on press in a full-screen viewer (`photo-viewer`, created by a script on first open): it closes with the cross, a press outside the photo or Escape. Without JavaScript and on a Ctrl/Cmd press the photo opens in a new tab by the same private link with an access check. The `product-photo` component gets this behaviour through the `is-zoomable` parameter; it is not enabled in the kitchen and in requests.

The wish's "Description" field is called "Product link" and became single-line; the value is still stored in `wishlist_items.description`. If it holds an address with `http://` or `https://`, the card shows a "Product link" button that opens the site in a new tab with `rel="noopener noreferrer"`; earlier text descriptions and addresses with other schemes are shown as plain text.

The app icon was replaced with a pixel heart on a dark background that is not tied to the theme: `public/icons/love-v3-180.png`, `love-v3-192.png` and `love-v3-512.png` (the last one is maskable, with the heart inside the safe zone). It was drawn as a vector in the same work; `love-v2-*` were removed. The service worker cache got version `v3`, and the offline screen stayed `offline-v2.html`. A previously installed app may update its icon only after being reinstalled.

## The dark "Pixel night" theme (0.31)

The fifth personal theme follows the icon's style: an almost black background, a coral red accent and the `pixels` decoration — pixel hearts in the header and on the background. Migration `add_pixel_theme_to_appearance_settings` appends the theme to the saved settings; the rollback removes it, and those who chose it will see the shared theme.

Dark themes are detected by the brightness of the background start (`AppearanceStore::isDark`), and `body` gets `data-scheme="dark"`. For them the styles switch surfaces to translucent white, text on accent buttons and labels to dark (`--color-on-accent`), and the error and success colours to light ones, and the browser is told to use the dark scheme for date fields and scrollbars. The toast takes its text colour from the theme background, and the dimming under dialogs and the photo viewer became black regardless of the theme. Any custom dark theme from the admin panel gets the same behaviour; the live theme preview in the admin panel does not switch the scheme.

The list of earlier draws was removed from the "Who will do it?" page by the owner's decision of 12 October 2026: only the new draw form stays there. A pending draw opens from the card on the home screen and after creation; a drawn chore opens from "Our duties".

## Interface language (0.32)

The interface is available in English and Russian, with English as the default. Every text in Blade and PHP goes through `__()` with the Russian phrase as the key, and `lang/en.json` holds the English text for each key; Russian needs no file of its own. The `SetLocale` middleware picks the language for each request: the signed-in user's `users.locale`, otherwise the device's `locale` cookie, otherwise `APP_LOCALE`. The page sets `<html lang>` accordingly, and dates follow the same language.

The profile has a "Language" section under "Additional settings": a select with each language named in itself, saved with `PATCH locale`. Guest pages show a two-button switch under the content; it stores only the cookie. Texts that JavaScript shows on its own (request errors, the sticker and presence statuses, builder hints) come from the `app-messages` meta tag filled by `ScriptMessages` and are read through `resources/js/modules/messages.js`.

Theme names, catalog names, statuses and enum labels are translated when shown. Names and descriptions typed by people are shown as written. The offline screen and the service worker's fallback are static, so they show both languages. The web manifest is static too and keeps the name «Любимость».

To add a text, wrap the Russian phrase in `__()` and add the same key to `lang/en.json`; `LocaleTest` fails if a key has no English text or its `:placeholders` differ. Check both languages at 360/390 px: English strings are often longer, so make sure buttons and the header do not overflow.
