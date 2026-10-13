<p align="center">
  <img alt="Relationship-Assistant" src="public/icons/love-v3-192.png" width="120">
</p>

<h1 align="center">Relationship-Assistant</h1>

<p align="center">
  <b>An open-source, self-hosted web app for couples: requests, shared plans and gratitude, all in one place.</b><br>
  Ask for something, agree on terms, confirm it was done and say thanks with hearts,<br>
  on your own laptop, with your own data, from any phone on your network.
</p>

<p align="center">
  <img alt="PHP 8.4" src="https://img.shields.io/badge/PHP-8.4-777bb4?style=flat-square&logo=php&logoColor=white">
  <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-ff2d20?style=flat-square&logo=laravel&logoColor=white">
  <img alt="MariaDB 11.4" src="https://img.shields.io/badge/MariaDB-11.4-003545?style=flat-square&logo=mariadb&logoColor=white">
  <img alt="Tailwind CSS 4" src="https://img.shields.io/badge/Tailwind%20CSS-4-06b6d4?style=flat-square&logo=tailwindcss&logoColor=white">
  <img alt="Vite 8" src="https://img.shields.io/badge/Vite-8-646cff?style=flat-square&logo=vite&logoColor=white">
  <img alt="Tested with Pest 4" src="https://img.shields.io/badge/tested%20with-Pest%204-f472b6?style=flat-square">
  <img alt="Version 0.31.0" src="https://img.shields.io/badge/version-0.31.0-d69e2e?style=flat-square">
  <img alt="License: MIT OR Apache-2.0" src="https://img.shields.io/badge/license-MIT%20%2F%20Apache--2.0-3a3a3a?style=flat-square">
  <img alt="Self-hosted" src="https://img.shields.io/badge/self--hosted-Docker-2496ed?style=flat-square&logo=docker&logoColor=white">
</p>

<p align="center">
  <a href="#features">Features</a> ·
  <a href="#stack">Stack</a> ·
  <a href="#deploy-manually">Deploy manually</a> ·
  <a href="#deploy-with-an-ai-agent">Deploy with an agent</a> ·
  <a href="#optional-services">Optional services</a> ·
  <a href="#development">Development</a> ·
  <a href="#documentation">Documentation</a> ·
  <a href="#license">License</a>
</p>

<br>

<table>
  <tr>
    <td width="25%" valign="top">
      <h3>💞 Built for two</h3>
      Accounts, couples and invitations, with weekly agreements, requests and counter-terms that both partners have to accept.
    </td>
    <td width="25%" valign="top">
      <h3>❤️ A hearts economy</h3>
      Completed duties earn hearts. A personal ledger, rewards, penalties and limits keep the balance honest, and every completion is confirmed in two steps.
    </td>
    <td width="25%" valign="top">
      <h3>📱 Phone-first</h3>
      An installable PWA in English and Russian with light and dark themes, plus a Telegram bot for sign-in, notifications and one-tap confirmations.
    </td>
    <td width="25%" valign="top">
      <h3>🤖 Agent-ready</h3>
      The repo ships its own agent instructions, a mandatory code style and a one-command quality gate, so an AI agent can deploy and extend it from a single prompt.
    </td>
  </tr>
</table>

<br>

<table>
  <tr>
    <td width="25%"><img src="docs/images/home.png" alt="Home screen: greeting, partner, day streak, anniversary, partner's mood, heart balance and quick actions" width="100%"></td>
    <td width="25%"><img src="docs/images/requests.png" alt="Request list grouped into awaiting an answer, in progress and completed" width="100%"></td>
    <td width="25%"><img src="docs/images/duty.png" alt="Duty card with stages, performer, checker, deadline, reward and the completion answer" width="100%"></td>
    <td width="25%"><img src="docs/images/kitchen.png" alt="Kitchen with regular and special dishes, grocery estimates and prices in hearts" width="100%"></td>
  </tr>
  <tr>
    <td align="center"><sub>Home</sub></td>
    <td align="center"><sub>Requests</sub></td>
    <td align="center"><sub>A duty</sub></td>
    <td align="center"><sub>Kitchen</sub></td>
  </tr>
</table>

<sub>Screenshots show the default "Pumpkin latte" theme with demo data.</sub>

> [!NOTE]
> The interface is available in **English and Russian**. English is the default; each person picks their own language in the profile, and Telegram notifications follow it. The guides come in both languages too: [English](docs/en/) and [Русский](docs/ru/).

## Features

<table>
  <tr>
    <td width="50%" valign="top">
      <h3>Requests and terms</h3>
      Text requests with deadlines, partner replies, counter-terms and a full history. Weekly agreements, "Really want" limits and versioned terms keep things fair.
      <br><br>
      See <a href="docs/en/requests.md">requests</a> and <a href="docs/en/couples.md">couples and invitations</a>.
    </td>
    <td width="50%" valign="top">
      <h3>Duties and hearts</h3>
      Duties are grouped by status and confirmed in two steps: one partner marks it done, the other checks. Hearts, rewards and penalties are recorded in a personal ledger, and a chore roulette decides who goes first.
      <br><br>
      See <a href="docs/en/hearts-and-duties.md">duties and hearts</a>.
    </td>
  </tr>
  <tr>
    <td width="50%" valign="top">
      <h3>Kitchen, wishlist and money</h3>
      A dish catalog with private photos, a wishlist with product links and gift states, and money requests with exact amounts and a separate "received" confirmation. No bank transfers are made.
      <br><br>
      See <a href="docs/en/dishes-wishlist-money.md">kitchen, wishes and money</a>.
    </td>
    <td width="50%" valign="top">
      <h3>Friends, guests and dates</h3>
      Personal friend lists, guest visits, dates with a time and dress code, a date wheel and a couple streak.
      <br><br>
      See <a href="docs/en/friends-guests-dates.md">friends, guests and dates</a>.
    </td>
  </tr>
  <tr>
    <td width="50%" valign="top">
      <h3>Mood and reminders</h3>
      Mood check-ins with emoji and a 30-day history, a button to ask your partner how they feel, and one-off reminders.
      <br><br>
      See <a href="docs/en/mood-and-reminders.md">mood and reminders</a>.
    </td>
    <td width="50%" valign="top">
      <h3>Economy, catalogs and admin</h3>
      Versioned economy rules, service price reserves, starter catalogs, an owner-only admin panel with statistics and an audit log, and a theme builder.
      <br><br>
      See <a href="docs/en/economy-and-catalogs.md">economy and catalogs</a> and <a href="docs/en/operations.md">operations</a>.
    </td>
  </tr>
</table>

<table>
  <tr>
    <td width="30%" valign="top"><img src="docs/images/language.png" alt="Profile settings with the Language section open and English selected" width="100%"></td>
    <td width="70%" valign="top">
      <h3>Your language, your theme</h3>
      Under "Additional settings" in the profile each person chooses the interface language and a personal theme. The choice applies only to them: one partner can use English while the other stays in Russian, and each gets Telegram notifications in their own language.
      <br><br>
      Sign-in and registration pages have a language switch as well. Names and descriptions you type yourself are never translated.
      <br><br>
      See <a href="docs/en/accounts.md#interface-language-032">interface language</a> and <a href="docs/en/interface.md">interface</a>.
    </td>
  </tr>
</table>

## Stack

- **Backend:** PHP 8.4, Laravel 13, MariaDB 11.4. Sessions, cache and the queue use the database by default.
- **Frontend:** Blade, Tailwind CSS 4 and Vite 8, with a service worker and web manifest for PWA install.
- **Realtime:** Laravel Reverb, as an optional separate service.
- **Runtime:** Docker through Laravel Sail. Nothing but Docker, PHP and Composer is needed on the host.
- **Quality:** Pint, PHPStan/Larastan and Pest, run by GitHub Actions on every push and pull request.

## Deploy manually

You need Docker Desktop (or Docker Engine with Compose), plus PHP 8.4+ and Composer on the host to install dependencies.

```sh
git clone https://github.com/ZeroKJ123/Relationship-Assistant.git
cd Relationship-Assistant

composer install
cp .env.example .env
php artisan key:generate

./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed --class=EconomySettingsSeeder
./vendor/bin/sail artisan db:seed --class=CatalogSeeder
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
```

Then open http://localhost:8086 and register the first account.

| What | Address |
| --- | --- |
| App | http://localhost:8086 |
| MariaDB from the host | `127.0.0.1:33070` |
| Vite dev server | http://localhost:5176 |

Before the first `sail up`, review `.env`:

- **Password:** the example uses the database `homeassist`, the user `sail` and the password `password`. Set your own `DB_PASSWORD` for anything long-lived.
- **Linux:** set `WWWUSER` and `WWWGROUP` to the output of `id -u` and `id -g`.
- **Language:** `APP_LOCALE` sets the language for guests and new accounts: `en` or `ru`.
- **Ports:** change `APP_PORT`, `APP_URL`, `FORWARD_DB_PORT` and `VITE_PORT` if the defaults are taken.
- **Phones on your network:** ports are bound to this computer only. Set `APP_BIND_ADDRESS=0.0.0.0` and put the laptop's IP and port in `APP_URL` to open the app to a trusted local network.

Day-to-day commands:

```sh
./vendor/bin/sail up -d     # start
./vendor/bin/sail stop      # stop, keeping the database
./vendor/bin/sail logs      # logs
```

The Compose project is named `homeassist`. It has its own containers, network and data volume (`homeassist_sail-mariadb`), and it starts again after Docker restarts unless you stopped it yourself.

## Deploy with an AI agent

Any coding agent that can run shell commands (Claude Code, Codex, Cursor and similar) can do the whole install. Open an empty folder in the agent and paste:

```text
Deploy Relationship-Assistant on this machine.

1. Clone https://github.com/ZeroKJ123/Relationship-Assistant.git and work inside it.
2. Read AGENTS.md and the "Deploy manually" section of README.md, and follow them exactly.
3. Check that Docker is running and that PHP 8.4+ and Composer are installed.
   If something is missing, stop and tell me what to install.
4. Create .env from .env.example, generate the app key and set a random DB_PASSWORD.
   On Linux, set WWWUSER and WWWGROUP from `id -u` and `id -g`.
   If port 8086, 33070 or 5176 is taken, pick a free one and update APP_URL to match.
5. Start the containers, run the migrations, seed EconomySettingsSeeder and CatalogSeeder,
   then run `npm ci` and `npm run build` through Sail.
6. Verify: `docker compose ps` shows healthy containers, `artisan migrate:status` shows
   no pending migrations, and the app URL answers with HTTP 200.
7. Report the URL, the ports you chose and anything you skipped.

Do not commit .env, do not print secrets, and do not enable Telegram, realtime or the
admin panel unless I ask.
```

To let the agent also expose the app to phones, add: `Make it reachable from my local network and tell me the address to open on the phone.`

For agents working on the code itself, [`AGENTS.md`](AGENTS.md) and [`CLAUDE.md`](CLAUDE.md) carry the project rules, [`CodeStyle.md`](CodeStyle.md) is the mandatory standard, and `composer check` is the gate that has to pass before a push.

## Optional services

Everything here is off by default and lives in `compose.workers.yaml`. Start the extra processes after the migrations are applied:

```sh
docker compose -f compose.yaml -f compose.workers.yaml --profile telegram-polling up -d
docker compose -f compose.yaml -f compose.workers.yaml ps
```

| Service | What it does | Setup |
| --- | --- | --- |
| `scheduler` | Checks overdue duties and sends reminders | [Duties and hearts](docs/en/hearts-and-duties.md) |
| `telegram-worker` | Delivers bot notifications from the queue | [Telegram and scheduling](docs/en/telegram-and-scheduling.md) |
| `telegram-poller` | Receives bot messages by polling, with no public address | [Telegram and scheduling](docs/en/telegram-and-scheduling.md) |
| `realtime` | Live updates over WebSocket with Laravel Reverb | [Operations](docs/en/operations.md) |

- **Telegram:** set `TELEGRAM_ENABLED=true`, a bot token and a bot username. Use `TELEGRAM_MODE=polling` on a laptop, or `webhook` behind a public HTTPS address. Never run both for the same bot.
- **HTTPS and PWA install:** installing the PWA on a phone needs trusted HTTPS. Put a TLS proxy such as a Cloudflare Tunnel in front, set `APP_URL=https://your-domain` and `TRUSTED_PROXIES`, and route `/app/*` to the `realtime` service.
- **Admin panel:** set `ADMIN_PANEL_LOGIN` and a bcrypt `ADMIN_PANEL_PASSWORD_HASH` to enable `/admin-panel`. Leave them empty to keep it off.

Backups, updates and restore checks are described in [operations](docs/en/operations.md).

## Development

```sh
./vendor/bin/sail npm run dev      # Vite with hot reload, until you stop it
./vendor/bin/sail npm run build    # rebuild assets after changes
./vendor/bin/sail composer check   # Pint, PHPStan/Larastan and Pest
```

Tests run on in-memory SQLite, and a guard refuses a persistent database, a connection URL or cached config. Race and constraint tests for couples use a throwaway MariaDB container; see [development](docs/en/development.md).

Where things live:

- **Routes:** `routes/web.php`
- **Views:** `resources/views/`
- **Styles and JavaScript:** `resources/css/app.css`, `resources/js/app.js`
- **Models:** `app/Models/`
- **Schema changes:** `database/migrations/`

[`CodeStyle.md`](CodeStyle.md) is mandatory for human developers and AI agents alike. Read it before implementing anything, and cite rule numbers in reviews.

## Documentation

Every guide exists in English ([`docs/en`](docs/en/)) and in Russian ([`docs/ru`](docs/ru/)). The links below lead to the English versions.

| Document | Covers |
| --- | --- |
| [Operations](docs/en/operations.md) | Local network, processes, backups, updates, HTTPS, admin panel |
| [Development](docs/en/development.md) | Commands, test isolation, quality checks |
| [Interface](docs/en/interface.md) | Components, accessibility, manual checks |
| [Accounts](docs/en/accounts.md) | Registration, recovery codes, migrations |
| [Couples and invitations](docs/en/couples.md) | Member rights, invitations, history, weekly limit |
| [Requests and terms](docs/en/requests.md) | Partner replies, deadlines, term versions, history |
| [Duties and hearts](docs/en/hearts-and-duties.md) | Completion checks, balance, ledger, rewards, limits |
| [Economy and catalogs](docs/en/economy-and-catalogs.md) | Rule versions, price reserves, templates |
| [Kitchen, wishes and money](docs/en/dishes-wishlist-money.md) | Dishes, private photos, gifts, exact money amounts |
| [Friends, guests and dates](docs/en/friends-guests-dates.md) | Friends, guest snapshots, meeting time, dress code |
| [Mood and reminders](docs/en/mood-and-reminders.md) | Ratings, personal schedule, one-off reminders |
| [Telegram and scheduling](docs/en/telegram-and-scheduling.md) | Sign-in, linking, quick buttons, delivery, schedule |

The release history is in [`version.json`](version.json).

## License

Relationship-Assistant is open source and dual-licensed under [MIT](LICENSE-MIT) or [Apache-2.0](LICENSE-APACHE), at your option.
Copyright (c) 2026 ZeroKJ123 and the Relationship-Assistant contributors.

The bundled Nunito font is licensed separately under the [SIL Open Font License](public/fonts/OFL.txt).
