# beauty-parlour-api

Owner-only backend API for a single beauty parlour's Android app.
The Android frontend is built separately in Flutter and talks to this API over HTTPS.

| Item | Value |
|------|-------|
| Framework | CodeIgniter 4 (installed: **4.7.5**, from the official `codeigniter4/appstarter`) |
| Language | PHP (local: **8.2.29**, WAMP; hosting: **8.3.35**, `ea-php83`, verified) |
| Dependency manager | Composer (local: **2.9.5**) |
| Database | **SQLite3** file at `writable/database/beauty_parlour.sqlite` (created on first connection). MySQL/MariaDB remains possible later via `.env` |
| Test runner | PHPUnit 10.5.66 (dev dependency only) |
| Deployment target | cPanel shared hosting (RoyalClouds, CloudLinux, **LiteSpeed**), document root inside `public_html` |

## Status

- **Step 0 - project scaffold - complete and verified (2026-10-23).**
- **Step 1 - hosting audit and database decision - complete (2026-10-23).** Default database
  group switched to SQLite3. Reasons and limits are under [Database](#database).
- **Phase 1 - database - complete (2026-10-23):** schema in [docs/database-schema.md](docs/database-schema.md),
  8 migrations, `SettingsSeeder`, 8 models, `appTimezone = Asia/Kolkata`, JSON API root,
  `GET /api/health`, web migration runner (`/install/*`) for the SSH-less host, release build
  script and [docs/deployment.md](docs/deployment.md).
- **Deployed 2026-10-23** to **`https://delicate.jainamagrifood.com/`** (project in `/home/<cpanel-user>/beauty-parlour-api/`,
  document root `public_html/delicate.jainamagrifood.com/`, SQLite 3.7.17, 8 migrations applied).
  The subdomain previously served a Node.js (Passenger) app; its `.htaccess` is kept as
  `.htaccess.passenger-backup-2026-10-23` next to the new one and in `deploy/`. The earlier
  `public_html/api/` document root was removed. Procedure: [docs/deployment.md](docs/deployment.md).
- **Continuous deployment:** pushing to `main` on GitHub runs tests, builds the release and deploys
  through the cPanel API (`.github/workflows/deploy-cpanel.yml`, `deploy/deploy-cpanel.sh`);
  see docs/deployment.md section 7b for the secrets to configure.
- **Phase 2 - owner authentication and Flutter API - complete (2026-10-23):** bearer-token login,
  settings, services, customers, appointments (items, overlap check, automatic WhatsApp reminders),
  payments, reminders with `wa.me` links, dashboard and reports. Endpoint reference:
  [docs/api.md](docs/api.md). Feature tests cover the whole flow on SQLite.
- **Phase 3 - mobile web UI - complete (2026-10-24):** server-rendered pages under `/app` behind
  `/login` (same owner phone + password, 30-day browser session, CSRF): dashboard, appointments
  (booking with customer autocomplete/quick-add, conflicts, status, payments), customers, services,
  payments, WhatsApp reminders with `wa.me` buttons, settings, password change, "Add to Home Screen"
  manifest. Gujarati labels. Live at `https://delicate.jainamagrifood.com/login`.
- **Phase 4 - automatic WhatsApp - complete (2026-10-24):** Baileys bridge, outbox, offer campaigns,
  cron sender; off by default. See `docs/whatsapp.md`.
- **Phase 5 - Gujarati end to end + auto WhatsApp on booking (2026-10-25, branch
  `claude/gujarati-ui-auto-whatsapp`):** default locale `gu` with `app/Language/gu/{App,Validation,Errors}.php`,
  every web page, flash message, validation message, status label, date ("25 ઑક્ટો 2026",
  "સવારે 10:30") and API error text in Gujarati; Gujarati WhatsApp templates (seeder + migration
  `2026-10-25-100001` that converts untouched English defaults); new `tpl_whatsapp_offer`; the
  "નવી બુકિંગ પર ઑટો WhatsApp" switch with bridge state and send window on `/app/settings`; queued
  confirmations follow reschedules/cancellations; owner guide in Gujarati
  (`docs/whatsapp-owner-guide.gu.md` and on the WhatsApp page). Deployment notes: `docs/deployment.md`
  section 8.
- See [Deliberately not done yet](#deliberately-not-done-yet) for what is still open.

## Scope of this release

- Exactly one beauty parlour and one owner. No multi-tenant logic, no roles beyond the owner.
- No customer-facing app or public registration.
- WhatsApp: **manual click-to-chat only.** The API will at most build `https://wa.me/<number>?text=...`
  links that the Flutter app opens in the owner's own personal WhatsApp.
  No unofficial WhatsApp automation libraries, no message sending from the server.

## Requirements

### PHP

- PHP **8.2 or newer**. Both `composer.json` and the framework pin `php: ^8.2`.
- Hosting runs `ea-php83` (8.3.35) for every vhost. Local development uses 8.2.29; PHP 8.2
  reaches end of security support on **2026-12-31**, so upgrading the local WAMP PHP to 8.3 is
  advisable but not required.

### PHP extensions

| Extension | Why | Local | Hosting (verified 2026-10-23) |
|-----------|-----|-------|-------------------------------|
| `intl` | Required by the framework | loaded | loaded (ICU 77.1) |
| `mbstring` | Required by the framework | loaded | loaded |
| `json` | Required by the framework (built in) | loaded | loaded |
| `sqlite3` | **Database driver used by this project** (CI4's SQLite3 driver uses the `SQLite3` class, not PDO) | loaded, SQLite 3.39.2 | loaded, **SQLite 3.7.17** |
| `mysqli` + `mysqlnd` | Only if the project moves to MySQL/MariaDB later | loaded | loaded |

Recommended and present on both: `curl`, `openssl`, `fileinfo`, `sodium` (Encryption library),
`gd`, `zip`, `pdo_sqlite`.

Development-only (PHPUnit): `ctype`, `dom`, `filter`, `libxml`, `phar`, `tokenizer`, `xmlwriter`;
Xdebug for code coverage. All present locally.

### Composer

Composer 2.x. The hosting account has no shell, so Composer never runs on the server; see
[Dependencies](#dependencies).

## Project layout

```
beauty-parlour-api/
├── app/                  Application code (controllers, models, config, filters, ...)
│   ├── Config/           App.php, Database.php, Routes.php, Filters.php, ...
│   └── .htaccess         Deny all (defense in depth only)
├── public/               ** WEB DOCUMENT ROOT ** - the only folder a browser may reach
│   ├── index.php         Front controller
│   └── .htaccess         mod_rewrite -> index.php, forwards Authorization header
├── writable/             Cache, logs, sessions, uploads (writable by PHP; .htaccess denies web access)
│   └── database/         SQLite database file(s); git-ignored except index.html
├── tests/                PHPUnit tests (SQLite in-memory; .htaccess denies web access)
├── docs/                 database-schema.md, deployment.md
├── deploy/               build-release.ps1, env.production.example, docroot-index.php
├── build/                Release zips and PHPUnit output (git-ignored)
├── vendor/               Composer packages incl. the framework (git-ignored, no .htaccess)
├── env                   Template for .env (committed)
├── .env                  Per-environment settings (git-ignored, NEVER commit or upload)
├── spark                 CLI entry point: php spark <command>
├── composer.json / .lock Dependencies
├── phpunit.dist.xml      Test configuration
├── preload.php           Optional opcache preload script (not used on shared hosting)
└── builds                Starter script to switch to the framework dev repo (not used)
```

## Local development

### First-time setup on a new machine

```bash
composer install
```

Then create `.env` from the template and set `CI_ENVIRONMENT = development` and `app.baseURL`:

```bash
cp env .env
```

(Windows cmd: `copy env .env`.) On this machine `.env` already exists with development values.
No database settings are needed locally: the SQLite file is created on first connection.

### Run the development server

```bash
php spark serve
```

Serves `http://localhost:8080/`. If the port is busy: `php spark serve --port 8085`
and update `app.baseURL` in `.env` to match.

### Database: migrate and seed

```bash
php spark migrate
```

```bash
php spark db:seed SettingsSeeder
```

`migrate` creates `writable/database/beauty_parlour.sqlite` on first run. `php spark migrate:status`
shows the batch table, `php spark migrate:rollback` drops the last batch (all tables of batch 1).

### Maintenance commands (cron on the server)

```bash
php spark db:backup --keep 14
```

```bash
php spark reminders:generate --dry-run
```

```bash
php spark whatsapp:send
```

`db:backup` copies the database with `SQLite3::backup()` into `writable/backups/` and keeps the
newest 14 files. `reminders:generate` creates the day's birthday, follow-up and missing appointment
reminders (idempotent; `--dry-run` only prints). `whatsapp:send` delivers the outbox through the
WhatsApp bridge (every minute; no-op while automatic sending is off). All three are cPanel cron
jobs on the server; see `docs/deployment.md` section 9 for the exact lines.

### Automatic WhatsApp (Phase 4)

`whatsapp-bridge/` is a small Node.js service (Baileys) that keeps the parlour's WhatsApp number
linked and sends the messages the PHP outbox queues: booking confirmations, due reminders, birthday
and follow-up messages and offer broadcasts (`/app/campaigns`). It is off until the owner links a
number and switches auto-send on at `/app/whatsapp`. Setup, limits and risks: `docs/whatsapp.md`.

### Routes (current)

| Route | Purpose |
|-------|---------|
| `GET /` | Browser: 302 to `/login` (or `/app` with a web session). `Accept: application/json`: the JSON API root |
| `GET /health` | Liveness check: framework, PHP, environment, timezone, database driver/version. Production: `https://delicate.jainamagrifood.com/health` |
| `GET /install/status?key=…` | Applied and pending migrations (404 without the exact `install.key` from `.env`) |
| `GET /install/migrate?key=…` | Runs pending migrations, `SettingsSeeder` and `ServicesSeeder` (standard price list, insert-only; same protection) |
| `POST /install/owner?key=…` | One-time creation of the owner login |
| `POST /auth/login` and the JSON API | For the Flutter app, see [docs/api.md](docs/api.md) |
| `GET /login`, `/app/...` | Mobile web UI (browser session). Pages: `/app` dashboard, `/app/appointments`, `/app/customers`, `/app/services`, `/app/payments`, `/app/reminders`, `/app/settings`, `/app/password`, `/app/more` |
| anything else | JSON 404 |

### Web UI notes

- Controllers in `app/Controllers/Web/`, views in `app/Views/web/` with the layout
  `app/Views/layouts/app.php`, one stylesheet `public/assets/app.css` and a small
  `public/assets/app.js` (customer autocomplete, live total, confirmations). No build step.
- Browser auth: `App\Filters\WebAuthFilter` (`webauth`) redirects to `/login`; `csrf` is applied to
  the same routes. Session lifetime is 30 days (`app/Config/Session.php`); cookies are `Secure`
  only in production (`app/Config/Cookie.php`).
- `App\Libraries\DashboardService` builds the home-screen data for both `GET /dashboard` (JSON)
  and `/app`.
- Display helpers (`rupees()`, `fmt_date()`, `gu_date()`, `gu_time()`, `status_label()`, ...) live in
  `app/Helpers/format_helper.php` and are autoloaded.
- Language: `app/Config/App.php` sets `defaultLocale = 'gu'`. Every user-facing string goes through
  `lang('App.key')` and lives in `app/Language/gu/App.php` (plus `Validation.php` and `Errors.php`
  for the framework's messages); `tests/unit/LanguageKeysTest.php` fails when a key used in `app/`
  is missing there. To add a language later, copy the `gu` folder to e.g. `en` and switch the
  locale per request (`$request->setLocale()`); enum values in the API never change.
- Local development: create a dev owner with `POST /install/owner?key=<install.key from .env>`,
  then open `http://localhost:8080/login`.

### Useful commands

```bash
php spark env
```

```bash
php spark routes
```

```bash
php spark namespaces
```

```bash
php spark config:check Database
```

```bash
php spark list
```

### Tests

```bash
php vendor/bin/phpunit --no-coverage
```

With coverage (needs Xdebug in coverage mode). Bash:

```bash
XDEBUG_MODE=coverage composer test
```

PowerShell:

```powershell
$env:XDEBUG_MODE='coverage'; composer test
```

Note: plain `composer test` runs green but **exits 1** because `phpunit.dist.xml` configures
coverage reports and `failOnWarning="true"`; without `XDEBUG_MODE=coverage` PHPUnit emits a
runner warning. Use one of the two forms above.

Tests use the `tests` database group (SQLite in memory, see `app/Config/Database.php`), so they
never touch `writable/database/`.

## Database

### Decision (2026-10-23)

The cPanel plan allows **3 MySQL databases and all 3 are in use** by other sites on the same
account, PostgreSQL is not installed on the server, and remote MySQL would add latency and an
external dependency. A read-only hosting audit plus a one-time runtime diagnostic confirmed that
PHP on the host has the `sqlite3` extension and can create and write SQLite files in the home
directory. The default database group is therefore **SQLite3**:

| Setting | Value | Why |
|---------|-------|-----|
| `DBDriver` | `SQLite3` | CI4 built-in driver (uses the `sqlite3` PHP extension) |
| `database` | `WRITEPATH . 'database/beauty_parlour.sqlite'` | Absolute path outside any document root; created on first connection |
| `foreignKeys` | `true` | SQLite leaves `PRAGMA foreign_keys` off unless asked |
| `busyTimeout` | `5000` ms | Web requests and cron jobs share one file; wait instead of failing with "database is locked" |
| `synchronous` | `null` | SQLite default (FULL); revisit only if measured |

Override the file location per environment in `.env` (absolute path only, `.env` cannot use the
`WRITEPATH` constant):

```
database.default.database = /home/<cpanel-user>/beauty-parlour-api/writable/database/beauty_parlour.sqlite
```

If RoyalClouds later grants a fourth MySQL database, switching is a `.env` change
(`DBDriver = MySQLi`, credentials, `charset = utf8mb4`, `DBCollat = utf8mb4_general_ci`), a re-run
of the migrations and a small data copy. Keep that path open by following the rules below.

### Rules forced by the hosting SQLite version (3.7.17, from 2013)

Verified in CI4 4.7.5's SQLite3 driver source:

- **Works:** connections, CRUD, Query Builder, transactions, `CREATE TABLE`, `CREATE INDEX`,
  `ADD COLUMN`, `DROP TABLE`, `insertBatch()`, `updateBatch()` (has a pre-3.33 fallback),
  `getFieldData()`, WAL journal mode.
- **Fails on the host:** `upsert()` / `upsertBatch()` (`ON CONFLICT` needs SQLite 3.24) and every
  Forge operation that rebuilds a table: `modifyColumn()`, `dropColumn()`, `dropForeignKey()`,
  `addPrimaryKey()`, `dropPrimaryKey()`. They call `getIndexData()`, which uses the
  `pragma_table_info()` / `pragma_index_xinfo()` table-valued functions (SQLite 3.16+).
- SQLite 3.7.17 itself also lacks window functions, CTEs (`WITH`), partial indexes,
  `RENAME COLUMN`, `RETURNING` and the JSON functions.

Therefore:

1. Write all schema with CI4 Forge and all queries with the Query Builder; no raw SQL, no `upsert()`.
2. A schema change on an existing table is a migration that creates the new table, copies the
   rows, drops the old table and renames; never `modifyColumn()`/`dropColumn()`.
3. Reports use plain `GROUP BY` aggregates or PHP-side aggregation.
4. Possible later improvement, not yet verified: the CloudLinux `alt-php83`/`alt-php84` builds may
   ship a newer SQLite. Check with a one-off cron job before relying on it, and remember that
   selecting alt-php in the PHP Selector changes PHP for every site on the account.

### File permissions and backups on shared hosting

- PHP runs as the cPanel account user (verified), so the database file is owned by the account.
  Target permissions: directory `writable/database/` **0700**, database file **0600**. SQLite also
  creates `-journal` / `-wal` / `-shm` files next to the database, so the directory itself must
  stay writable.
- The file must never sit under a document root. `writable/` is outside `public/` and carries a
  deny-all `.htaccess` as defense in depth.
- Backups: `php spark db:backup` copies the file through SQLite's online backup API
  (`SQLite3::backup()`) from a daily cron job into `writable/backups/` with rotation
  (`docs/deployment.md` section 9); download that folder periodically. A raw `cp` while a write is
  in progress can produce a corrupt copy. cPanel's account backups (enabled by the host) include the
  home directory and are a second line of defence, not the first.
- Single-owner workload means one writer at a time in practice; `busyTimeout` covers the overlap
  between a web request and a cron run.

## cPanel shared-hosting deployment

Facts below were verified on the actual hosting account on 2026-10-23 through cPanel's read-only
API and a one-time diagnostic script. **Nothing has been deployed yet.**

### Hosting facts that shape the deployment

| Fact | Consequence |
|------|-------------|
| Server rule `docroots_in_public_html_only` is on | Every document root must be inside `public_html`. The project cannot be served from a folder outside it. |
| All 5 subdomain slots are used, addon domains limit is 0 | No new hostname can be created. The API must reuse an existing hostname or a path under one. The `delicate.` subdomain currently runs a Node.js (Passenger) app with its own SQLite data. **Decision pending.** |
| No SSH, no cPanel Terminal (`noshell`) | `php spark` cannot be run interactively. Use the Cron Jobs feature (enabled) for one-off commands, or a protected web endpoint designed later. |
| PHP `ea-php83` 8.3.35 via LiteSpeed, runs as the account user | File permissions are simple (0700/0600 work). `open_basedir` and `disable_functions` are empty; `memory_limit` 512M; `max_execution_time` 30 s; OPcache installed but disabled. |
| MySQL 3/3 used (MariaDB 10.3.39), PostgreSQL unsupported | SQLite is the default; ask RoyalClouds for an extra database if MySQL is wanted later. |
| Git Version Control feature is available | A cPanel-side `git pull` deployment is possible later without SSH. |
| LVE limits: 1 GB RAM, 25 entry processes, I/O 1 MB/s | Fine for an owner-only API; avoid heavy report queries at peak. |
| Branded cPanel hostname has an expired/mismatched TLS certificate | Log in to cPanel through `https://<main-domain>:2083`, which has a valid certificate. |

### Layout on the server

1. Upload the project to `/home/<cpanel-user>/beauty-parlour-api/` (outside `public_html`).
2. The chosen hostname's document root (inside `public_html`) receives only the **contents** of
   `public/`: `index.php`, `.htaccess`, `robots.txt`, `favicon.ico`.
3. Edit that copied `index.php` so the Paths line points at the application, for example from
   `public_html/<hostname>/`:
   `require FCPATH . '../../beauty-parlour-api/app/Config/Paths.php';`
   If the API lives in a sub-folder of a site, also set `RewriteBase /that-folder/` in `.htaccess`.
4. The SQLite file stays at `/home/<cpanel-user>/beauty-parlour-api/writable/database/`, never
   under `public_html`.

Never upload the whole project into `public_html`. The `.htaccess` deny files in `app/`,
`writable/` and `tests/` are defense in depth only, and `vendor/` has none.

### Dependencies

Build locally, then upload `vendor/` with the project:

```bash
composer install --no-dev --optimize-autoloader
```

`--no-dev` leaves out PHPUnit, Faker and vfsStream. Never run `composer update` on the server.

### `.env` on the server

Create it by hand on the server (copy `env` to `.env` there). Minimum:

- `CI_ENVIRONMENT = production`
- `app.baseURL = 'https://<hostname>/'` (trailing slash)
- `app.forceGlobalSecureRequests = true` (the account has AutoSSL certificates)
- `database.default.database = /home/<cpanel-user>/beauty-parlour-api/writable/database/beauty_parlour.sqlite`
- `encryption.key` when the authentication phase requires it

### Running `php spark` without SSH

Create a one-off cron job in cPanel » Cron Jobs with an explicit PHP binary, redirecting output to
a file in the home directory, then delete the job. The server's PHP CLI binaries (verified present):

```
/opt/cpanel/ea-php83/root/usr/bin/php /home/<cpanel-user>/beauty-parlour-api/spark migrate > /home/<cpanel-user>/tmp/spark.log 2>&1
```

The cron PHP binary's SQLite version has **not** been verified yet; check it the same way before
the first production migration.

### Authorization header

The shipped `public/.htaccess` forwards the `Authorization` header to PHP. LiteSpeed honours
`.htaccess`; keep that rule for token-based authentication later.

### Production optimizations

Run `php spark optimize` (config and locator caching) through a cron job after each deploy and
after any config change. Clear `writable/cache/` when configuration changes.

## Verification results

### Local (2026-10-23, Windows 11, WAMP PHP 8.2.29)

Every row was actually executed; results are copied from the output.

| Command | Result |
|---------|--------|
| `php -v` | PHP 8.2.29 (cli), Xdebug 3.4.7, OPcache |
| `composer -V` | Composer 2.9.5 |
| `php -m` + extension check | `intl`, `mbstring`, `json`, `sqlite3`, `pdo_sqlite`, `mysqli`, `mysqlnd`, `curl`, `openssl`, `fileinfo`, `gd`, `sodium`, `zip` loaded |
| `composer create-project codeigniter4/appstarter beauty-parlour-api` | appstarter v4.7.5 + framework v4.7.5, 33 packages, no security advisories |
| `composer validate` / `check-platform-reqs` / `audit` | valid / all 11 requirements success / no advisories |
| `php spark env` / `namespaces` / `routes` | development / 10 namespaces found / `GET /` -> `Home::index` |
| `php spark serve --port 8080` | `GET /` HTTP 200, unknown route HTTP 404, stopped cleanly |
| `php vendor/bin/phpunit --no-coverage` | `OK (5 tests, 7 assertions)` before and after the SQLite switch |
| `XDEBUG_MODE=coverage composer test` | `OK (5 tests, 7 assertions)`, exit 0 |
| `php spark config:check Database` | default group: `SQLite3`, file under `writable/database/`, `foreignKeys` true, `busyTimeout` 5000 |
| `php spark migrate` / `migrate:status` | 8 migrations applied in batch 1; FK clauses rendered as `ON DELETE RESTRICT ON UPDATE CASCADE` etc.; 14 indexes created |
| `php spark db:seed SettingsSeeder` | 13 default settings inserted; re-running inserts nothing |
| `php vendor/bin/phpunit --no-coverage` (after Phase 1) | `OK (10 tests, 24 assertions)` incl. `SchemaMigrationTest` (tables, seeder idempotency, unique phone, CASCADE/RESTRICT, `PRAGMA foreign_keys`) |
| `php spark serve` + HTTP checks | `GET /` 200 JSON; `GET /api/health` 200 with `"database":{"ok":true,"driver":"SQLite3"}` and `"timezone":"Asia/Kolkata"`; `/install/status` 404 without or with wrong key, 200 with key; `/install/migrate` 200 `"migrated":true`; unknown route 404 JSON |
| `deploy\build-release.ps1` | zip 1.51 MB, 1089 entries, only `codeigniter4/framework`, `laminas/laminas-escaper`, `psr/log` in vendor; no `.env`, `tests/`, `.sqlite`, logs |

### Hosting (2026-10-23, read-only cPanel API + one-time self-deleting diagnostic)

| Check | Result |
|-------|--------|
| PHP runtime | 8.3.35, SAPI `litespeed`, process user = account user |
| Extensions | `sqlite3`, `pdo_sqlite`, `PDO`, `mysqli`, `mysqlnd`, `pdo_mysql`, `intl`, `mbstring`, `json`, `curl`, `openssl`, `fileinfo`, `sodium`, `gd`, `zip`, `posix` all loaded |
| SQLite library | 3.7.17; create / WAL switch / insert / select / `foreign_keys` ON / cleanup in `$HOME/tmp`: PASS |
| MySQL | MariaDB 10.3.39 local, 3 of 3 databases used (plan limit) |
| PostgreSQL | "This server does not support this functionality" |
| Shell / Terminal / Cron | no shell, Terminal disabled, Cron enabled |
| Domains | main domain + 5 subdomains (5/5 used), addon domains 0, docroots inside `public_html` only |
| Disk | 4.8 GB of 10 GB used, no inode limit |
| Diagnostic cleanup | script, `.htaccess` and folder deleted themselves; confirmed absent via API listing |

Not verified yet: SQLite version of the cron PHP binaries, `.htaccess` behaviour of the final
hostname, HTTPS end to end for the API hostname.

## Deliberately not done yet

- Birthday reminders (a daily `spark` command run from cPanel cron) and the SQLite backup command.
- Importing data from the previous Node.js prototype (`~/delicate-data/delicate.sqlite`).
- Phase 4 (decision pending): WhatsApp sending through a separate Baileys bridge (`whatsapp_outbox`
  table + bridge endpoints); click-to-chat stays as the fallback.
- Customers delete policy (soft delete with restore vs hard delete) - open owner decision.
- Choice of the hostname/path the API will be served from on the hosting account, and the upload
  itself (see `docs/deployment.md`).
- Version control: this folder is **not** a git repository yet (`.gitignore` is in place).
- `license` decision (`composer.json` still says MIT).

## Upstream

Created from `codeigniter4/appstarter` v4.7.5. To update the framework:

```bash
composer update codeigniter4/framework
```

Then read the release notes and compare `app/` with `vendor/codeigniter4/framework/app` for
config changes that need to be copied over.

User guide: https://codeigniter.com/user_guide/
