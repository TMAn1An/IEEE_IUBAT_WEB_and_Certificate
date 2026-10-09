# IEEE IUBAT Student Branch — Website & Certificate System

A Laravel 12 application containing:

- The public IEEE IUBAT Student Branch website.
- An admin-only certificate system: template management, single and bulk certificate generation,
  QR-code verification, revocation/reissue, and audit logging.

Built to run on ordinary cPanel shared hosting (PHP 8.2, MySQL) — no Docker, Redis, or Node
process required in production.

## Start here

- **`CLAUDE.md`** — permanent project rules. Read this before making any change.
- **`docs/PROJECT_REQUIREMENTS.md`** — what this project is and its acceptance criteria.
- **`docs/ARCHITECTURE.md`** — folder layout, data flow, chosen packages and why.
- **`docs/DATABASE_DESIGN.md`**, **`docs/CERTIFICATE_SYSTEM.md`**, **`docs/TEMPLATE_EDITOR.md`** —
  the certificate system in detail.
- **`docs/FORM_BUILDER.md`**: the general-purpose dynamic Form Builder (admin builder, public
  `/forms/{slug}`, submissions, Excel export).
- **`docs/SECURITY.md`**, **`docs/TESTING.md`** — the security and testing checklists.
- **`docs/DEPLOYMENT_CPANEL.md`** — how this gets deployed to the target hosting.
- **`docs/MIGRATION_PLAN.md`** — the exact URL map being preserved from the original site.
- **`docs/CHANGELOG.md`** — what's changed, phase by phase.

## Local development

This project develops against **Laravel Sail** (Docker) so the local PHP version matches
production exactly (PHP 8.2), regardless of what PHP version is installed on the host machine.

```bash
# first time
cp .env.example .env     # already done if you cloned this repo with its committed .env.example
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate

# everyday use
./vendor/bin/sail up -d
./vendor/bin/sail artisan ...
./vendor/bin/sail composer ...
./vendor/bin/sail npm ...
./vendor/bin/sail test
./vendor/bin/sail down
```

The app will be available at `http://localhost`.

### Windows + Docker Desktop: one-time permissions fix

On Windows, Docker Desktop's bind mount can present `storage/` and `bootstrap/cache/` as
`root:root` inside the container, which the container's `sail` user (uid 1000) can't write to —
this breaks compiled view caching (`tempnam(): file created in the system's temporary directory`,
followed by a 500 error) the first time you request a page. If you hit that, run once after
`sail up`:

```bash
docker compose exec laravel.test chmod -R ugo+rwX storage bootstrap/cache
```

Not needed on macOS/Linux hosts, and not relevant to production (which doesn't use Docker at all —
see `docs/DEPLOYMENT_CPANEL.md`).

## Reference material (not part of the running application)

- `reference/legacy-site/` — the original plain-PHP site this project migrates from. Kept for
  visual/content comparison during the migration; never executed as part of this app.
- `reference/qr-generator/` — the archived Flask/openpyxl QR-generator prototype this project's
  certificate system replaces. Read-only reference for its business-logic concepts; never run.
