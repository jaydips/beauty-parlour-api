# Deployment to cPanel shared hosting

Target account facts (verified 2026-10-23): cPanel on CloudLinux with LiteSpeed, PHP `ea-php83`,
no SSH or Terminal, Cron Jobs available, document roots must be inside `public_html`, every
subdomain slot already used. See README "cPanel shared-hosting deployment" for the full list.

## 0. Decide the hostname (one-time)

No new subdomain can be created on the account. Options:

| Option | What changes | Result URL |
|--------|--------------|------------|
| A. Folder under an existing PHP site | add `public_html/api/` (or similar) next to the WordPress files | `https://<main-domain>/api/` |
| B. Re-use the `delicate.` subdomain | its current Node.js (Passenger) app stops being served | `https://delicate.<main-domain>/` |
| C. Ask RoyalClouds for one more subdomain slot | plan change on the provider side | `https://api.<main-domain>/` |

**Chosen on 2026-10-23: option B.** The API is served from `https://delicate.jainamagrifood.com/`
(document root `public_html/delicate.jainamagrifood.com/`). The Node.js app's Passenger
`.htaccess` was saved as `.htaccess.passenger-backup-2026-10-23` in that document root and as
`deploy/delicate-htaccess.passenger-backup-2026-10-23.txt`; restoring that file (and stopping the
API) brings the Node app back. The app was first deployed under option A (`public_html/api/`) for a
few hours; that document root has been removed.

The generic examples below still use option A's folder name `api`; for the live setup read
`public_html/api/` as `public_html/delicate.jainamagrifood.com/`, `RewriteBase /api/` as no
`RewriteBase`, and `https://<main-domain>/api/` as `https://delicate.jainamagrifood.com/`.

## 1. Build the release on the development machine

```powershell
powershell -ExecutionPolicy Bypass -File deploy\build-release.ps1
```

Produces `build\release\beauty-parlour-api-<timestamp>.zip` with production dependencies only
(no `.env`, no `tests/`, no local data). Takes under a minute.

## 2. Put the application folder OUTSIDE public_html

1. cPanel » File Manager » navigate to `/home/<cpanel-user>/` (the home directory, not public_html).
2. Upload the zip, select it, **Extract**. This creates `/home/<cpanel-user>/beauty-parlour-api/`.
3. Delete the zip.

Through the API (what the 2026-10-23 deployment did): UAPI `Fileman/upload_files` to upload,
then cPanel API 2 `Fileman::fileop` with `op=extract`, `sourcefiles=/home/<user>/<zip>`,
**`destfiles=/home/<user>`** (without `destfiles` unzip fails with "Permission denied"),
`op=chmod` with `metadata=0755|0644|0700|0600`, and `op=unlink` to remove the zip. This cPanel
version has no UAPI `Fileman/delete_file`. The zip must be built with `deploy/make-zip.php`
(called by `build-release.ps1`) so it carries Unix permissions; a zip from Windows `tar`/
`Compress-Archive` extracts as 0777/0666.

## 3. Create the server `.env`

In File Manager, inside `/home/<cpanel-user>/beauty-parlour-api/`, create a file named `.env`
with the content of `deploy/env.production.example`, replacing every `<placeholder>`:

- `app.baseURL` = the URL chosen in step 0, with trailing slash (`https://<main-domain>/api/` for option A).
- `database.default.database` = absolute path to `writable/database/beauty_parlour.sqlite`.
- `install.key` = 64 random hex characters (PowerShell: `-join ((1..64) | % { '{0:x}' -f (Get-Random -Max 16) })`).

## 4. Fill the document root

For option A, create `public_html/api/` and copy into it the **contents** of
`/home/<cpanel-user>/beauty-parlour-api/public/`: `index.php`, `.htaccess`, `robots.txt`,
`favicon.ico` (File Manager » Copy). Then:

1. Replace `public_html/api/index.php` with `deploy/docroot-index.php` and set its Paths line to
   `'../../beauty-parlour-api/app/Config/Paths.php'` (from `public_html/api/`, `../..` is the home directory).
2. In `public_html/api/.htaccess` uncomment and set `RewriteBase /api/`.

For option B the document root is `public_html/delicate.<main-domain>/`: remove the Passenger
`.htaccess` there first, then copy the `public/` files and set the Paths line to the same `../../` value.

## 5. Permissions

PHP runs as the account user, so defaults work. Tighten the data folder in File Manager »
Permissions: `writable/database/` **0700**. After the first migration, set
`writable/database/beauty_parlour.sqlite` to **0600**. Keep `writable/` and its other
sub-folders at 0755.

## 6. Run the migrations through the web runner

```
https://<hostname>/install/status?key=<install.key>
https://<hostname>/install/migrate?key=<install.key>
```

The first call lists applied and pending migrations (expect 8 pending on a fresh install), the
second applies them and seeds the default settings. Both answer `404` without the exact key.
**Then comment out `install.key` in the server `.env`.** Re-enable it only for the next deploy.

Alternative without the web runner: cPanel » Cron Jobs, run once and delete:

```
/opt/cpanel/ea-php83/root/usr/bin/php /home/<cpanel-user>/beauty-parlour-api/spark migrate > /home/<cpanel-user>/tmp/spark-migrate.log 2>&1
```

## 7. Verify

```
https://<main-domain>/api/health
```

(`health` is the route; `/api/` is the folder the app is served from.)
Expect `"status":"ok"`, `"database":{"ok":true,"driver":"SQLite3","version":"3.7.17"}`,
`"environment":"production"`, `"timezone":"Asia/Kolkata"`. Any other path returns a JSON 404.

## 7b. Automatic deployment from GitHub (no PC needed)

The repository carries `.github/workflows/deploy-cpanel.yml`. On every push to `main` GitHub
Actions runs the test suite, builds the release zip with production dependencies and Unix
permissions, and runs `deploy/deploy-cpanel.sh`, which performs steps 2, 4, 6 and 7 above through
the cPanel API: upload zip, extract over `/home/<user>/beauty-parlour-api/`, delete zip, rewrite the
document-root files, call `/install/migrate`, check `/health`. The server `.env` and
`writable/database/` are never part of the zip, so data survives every deploy.

One-time GitHub setup:

| Kind | Name | Value |
|------|------|-------|
| secret | `CPANEL_HOST` | `jainamagrifood.com` |
| secret | `CPANEL_USER` | cPanel user name |
| secret | `CPANEL_TOKEN` | a cPanel API token created for GitHub (cPanel » Manage API Tokens) |
| secret | `CPANEL_HOME` | `/home/<cpanel-user>` |
| secret | `APP_URL` | public base URL without trailing slash: `https://delicate.jainamagrifood.com` |
| secret | `INSTALL_KEY` | the `install.key` value from the server `.env` |
| variable | `DOCROOT` | `/home/<cpanel-user>/public_html/delicate.jainamagrifood.com`; leave empty to skip the document-root sync |
| variable | `REWRITE_BASE` | `/` (subdomain root); `/api/` only for a sub-folder deployment |
| variable | `PATHS_RELATIVE` | path from the document root to the app folder, default `../../beauty-parlour-api` |

Changing the hostname later (for example moving to a subdomain root) is a change of `APP_URL`,
`DOCROOT`, `REWRITE_BASE` and of `app.baseURL` in the server `.env`; no code change.

The same script runs from a developer machine too (Git Bash) with the same variables exported.

## 7c. Web UI assets

The document-root sync (`deploy/deploy-cpanel.sh` step 4, and step 4 of the manual procedure) also
copies `public/manifest.webmanifest` and every file in `public/assets/` (`app.css`, `app.js`,
`icon-192.png`, `icon-512.png`) into `<docroot>/assets/`. `public/.htaccess` serves
`.webmanifest` as `application/manifest+json`. After a deploy, open `https://<host>/login` and
check that the page is styled; an unstyled page means the assets were not copied.

## 8. Updating an existing deployment

1. Rebuild the zip (step 1) and upload it to the home directory.
2. Extract it **over** the existing folder (File Manager asks to overwrite; accept). The zip never
   contains `.env`, `writable/database/` data, logs or sessions, so they survive.
3. If `public/` changed, re-copy its files to the document root (keep your edited `index.php`).
4. Set `install.key` in `.env`, call `/install/migrate`, comment the key out again.
5. Check `/api/health`.

## 9. Backups (to be automated in a later phase)

Daily cron job calling a `spark` command that copies the database with `SQLite3::backup()` into
`writable/backups/` with rotation, plus periodic downloads of that folder. Until then: File Manager
» download `writable/database/beauty_parlour.sqlite` only when the parlour is closed.
