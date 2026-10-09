# cPanel Deployment

Target: PHP 8.2.31, Composer 2.9.7, MySQL/MariaDB, cPanel with Terminal/SSH, Git, Cron Jobs,
MultiPHP Manager/INI Editor, File Manager, SSL. No Docker, no Node process, no Redis, no Python at
runtime.

## Staging first — never deploy straight to production

1. Existing live site at the production domain stays untouched throughout development.
2. Deploy Laravel to a staging subdomain (e.g. `staging.ieee.iubat.edu` or similar — confirm with
   the user which subdomain to use in cPanel's Subdomains tool).
3. On staging: compare every public page against the live site, test the full certificate/admin
   flow, test on mobile, and **scan a generated QR with a real phone** against the staging URL.
4. Only after the user explicitly approves, back up the live site and its data, then switch
   production over per §Go-live below.

## Document root layout

Two supported layouts depending on what this specific cPanel account allows:

**Preferred — Laravel outside the web root:**
```
/home/<user>/ieee-cert-app/          <- full Laravel project (not web-accessible)
/home/<user>/ieee-cert-app/public/   <- symlinked or copied to the domain's document root
/home/<user>/public_html/            <- domain document root, containing only Laravel's public/ contents
```
Achieved either by setting the domain/subdomain's document root directly to
`/home/<user>/ieee-cert-app/public` in cPanel (if the account allows a document root outside
`public_html`), or by symlinking. This keeps `.env`, `app/`, `vendor/`, `storage/` outside any
directly HTTP-reachable path.

**Fallback — if the account can't point the document root outside `public_html`:**
```
/home/<user>/ieee-cert-app/          <- Laravel project, sibling to public_html
/home/<user>/public_html/            <- copy of Laravel's public/ directory contents, with
                                         index.php's require paths adjusted to point at
                                         ../ieee-cert-app/vendor/autoload.php and
                                         ../ieee-cert-app/bootstrap/app.php
```
Either way, `.env`, `storage/`, and everything except the contents of `public/` must never be
directly HTTP-reachable. Verify by requesting `https://domain/.env` and
`https://domain/storage/app/private/...` after deploy — both must 404.

## Deployment steps

1. **Database**: create the MySQL database and database user via cPanel's MySQL Databases tool
   (this is the one place manual phpMyAdmin/cPanel DB creation is expected — schema itself is
   never hand-created, only the database/user).
2. **Code**: `git clone`/`git pull` the repository via cPanel Terminal/SSH into the project
   directory (see layout above).
3. **`.env`**: copy `.env.example` to `.env`, fill in real DB credentials, `APP_URL`, mail settings
   if used. Never commit this file.
4. **Dependencies**: `composer install --no-dev --optimize-autoloader`. This also installs the
   Form + Page Builder package `tman1an/formbuilder` from its public GitHub repository at the
   commit pinned in `composer.lock` (no credentials needed). See `docs/FORM_BUILDER.md`. The
   package serves its own CSS/JS and page images through routes, so it adds no asset or
   `storage:link` step. Uploads land in `storage/app/private/form-builder/submissions` (form
   images) and `storage/app/public/form-builder/pages` (page images).
5. **App key**: `php artisan key:generate` (only if `.env` doesn't already have one from a prior
   deploy — never regenerate on top of a live `.env` with existing encrypted data without a plan).
6. **Migrations**: `php artisan migrate --force` (`--force` required since `APP_ENV=production`
   blocks interactive migration prompts).
7. **Storage**: ensure `storage/` and `bootstrap/cache/` are writable by the web server user;
   `php artisan storage:link` only if the app ever needs a public storage symlink (current design
   in `docs/ARCHITECTURE.md` keeps certificate material on the `private` disk specifically to avoid
   needing this for certificate files — re-evaluate only if a genuinely public storage need shows
   up later).
8. **Caching**: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
9. **Frontend assets**: run `npm ci && npm run build` on a machine with Node (locally, or in CI —
   never on the production server) and upload the resulting `public/build/` output; the public
   site's own `public/assets/*` files are copied as static files, no build step for those.
10. **PHP version**: confirm MultiPHP Manager has this domain set to PHP 8.2 (ea-php82 or
    equivalent), matching `composer.json`'s `"php": "^8.2"` and the `config.platform.php` pin.

## Environment

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<the-actual-domain>
SESSION_SECURE_COOKIE=true   # once SSL is confirmed active on the domain
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
```

## Cron Jobs (cPanel Cron Jobs tool)

Two entries, both ordinary short-lived cron invocations — no persistent worker process, matching
the hosting constraint of no Redis/no daemon:

```
* * * * * cd /home/<user>/ieee-cert-app && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/<user>/ieee-cert-app && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

`schedule:run` drives Laravel's scheduler, which is where the ZIP-cleanup command
(`app:cleanup-expired-batches` or similar, per `docs/CERTIFICATE_SYSTEM.md`) gets registered —
default retention: delete a batch's ZIP and its `zip_path` reference after a configurable window
(suggested default: **7 days**; confirm/adjust with the user), leaving the underlying `certificates`
DB records and individual PDFs untouched. `queue:work --stop-when-empty` processes queued bulk-
generation jobs in short bursts rather than requiring a long-running worker, which fits shared
hosting's process limits.

## Cache/queue tables

Since Redis isn't used, the `database` driver needs its supporting tables (`cache`, `jobs`,
`sessions` etc.) created via the standard Laravel migrations for those drivers — included in the
normal `php artisan migrate` run, no extra manual step.

## First real admin account

Never seed real credentials into a committed seeder. Create the first Super Admin on the
production/staging server directly:

```
php artisan app:make-admin --name="..." --email="..." --role=super_admin
```

(A small Artisan command, built in Phase 2, that prompts for or accepts a password interactively/
via a one-time generated value — never a hardcoded password in source or in a seeder that ships to
production.)

## Backups

Before any production migration/switch:
- Back up the current `public_html` (the whole existing plain-PHP site) to a dated archive kept
  outside the web root.
- Document the current domain/subdomain configuration (document root, PHP version, SSL) as it
  stands before changes, so it can be restored if needed.

After launch, ongoing:
- Regular MySQL database backup (cPanel's Backup tool, or a scheduled `mysqldump` via cron to a
  location outside the web root).
- Regular backup of `storage/app/private` (certificate templates + generated certificate PDFs +
  batch imports) — this is generated, valuable, and not reproducible from the database alone.
- Temporary ZIP archives (`storage/app/private/batches/*/certificates.zip`) are explicitly
  **excluded** from what counts as a critical backup — they're regenerable from the DB + stored
  per-certificate PDFs and are cleaned up on their own schedule anyway.

## Rollback procedure

- Code: `git` makes reverting to a previous commit straightforward; keep the previous release's
  `vendor/` available or be ready to re-run `composer install` against the reverted commit.
- Database: migrations should be written rollback-safe (`down()` methods that actually reverse
  the `up()`) wherever realistically possible, so `php artisan migrate:rollback` is a real option;
  document any migration where a clean rollback isn't possible (e.g. a destructive data
  transformation) directly in that migration's file.
- Before any risky production migration step, take a fresh DB backup specifically for that
  deploy, separate from the regular schedule.

## Go-live (switching the live domain over)

Only after staging has been fully verified and the user has explicitly approved:
1. Final backup of the live `public_html` and its database.
2. Point the production domain's document root at the Laravel deployment (or swap
   `public_html`'s contents, per whichever layout was used).
3. Re-verify the production domain end-to-end (public pages, admin login, one real single
   certificate generation + verification + QR scan) before considering the migration complete.
4. Keep the pre-migration backup available for a defined retention window in case a rollback is
   needed.
