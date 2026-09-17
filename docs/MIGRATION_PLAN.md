# Migration Plan

## URL map that must keep working

Source of truth for this table: `.htaccess` (rewrite/redirect rules) and `sitemap.xml` as they
exist in the original plain-PHP site, read directly rather than assumed.

### Current canonical URLs (must render in Laravel, same content/design)

| URL | Source page | Laravel route (planned) |
|---|---|---|
| `/` | `index.php` | `PageController@home` |
| `/about` | `about.php` | `PageController@about` |
| `/committee` | `committee.php` | `PageController@committee` |
| `/contact` | `contact.php` | `PageController@contact` |
| `/events` | `events.php` | `PageController@events` |
| `/membership` | `membership.php` | `PageController@membership` |
| `/event/becithcon-2026` | `becithcon-2026.php` | `EventController@becithcon2026` |
| `/event/hta-2026` | `hta-2026.php` | `EventController@hta2026` |

### Legacy URLs that must 301-redirect to the canonical URL above

| Legacy URL | Redirects to | Original rule |
|---|---|---|
| `/index.html` | `/` | `.htaccess` rule 1 |
| `/about.html` | `/about` | `.htaccess` rule 1 |
| `/committee.html` | `/committee` | `.htaccess` rule 1 |
| `/contact.html` | `/contact` | `.htaccess` rule 1 |
| `/events.html` | `/events` | `.htaccess` rule 1 |
| `/membership.html` | `/membership` | `.htaccess` rule 1 |
| `/humanitarian-project-exhibition-2026` (`.html` or `.php` or bare) | `/event/hta-2026` | `.htaccess` rule (oldest slug) |
| `/hpe-2026` (bare or `.php`) | `/event/hta-2026` | `.htaccess` rule (2nd slug) |
| `/event/hpe-2026` | `/event/hta-2026` | `.htaccess` rule (3rd slug, pre-HTA-rename) |
| `/index.php` | `/` | `.htaccess` rule 2 |
| `/about.php`, `/committee.php`, `/contact.php`, `/events.php`, `/membership.php`, `/becithcon-2026.php`, `/hta-2026.php` | their clean-URL equivalent | `.htaccess` rule 2 (generic `.php` strip) |

In Laravel, none of these `.php`/`.html` URLs exist as real files any more, so they must be
explicit `Route::redirect('/about.html', '/about', 301)`-style entries (or a small helper that
registers the whole table from one array) in `routes/web.php`, rather than relying on any rewrite
trick. Implement and test every row in this table as part of Phase 1 — see the "every legacy URL
redirects correctly" requirement in `docs/TESTING.md`.

### Assets — same public paths

`/assets/css/style.css`, `/assets/js/main.js`, `/assets/js/particles.js`, `/assets/js/voxel-qr.js`,
`/assets/css/voxel-qr.css`, everything under `/assets/img/*` and `/assets/pdf/*`, `/robots.txt`,
`/sitemap.xml`, `/site.webmanifest` — all copied verbatim into Laravel's `public/` directory at
the same paths, so nothing referencing them (including already-indexed search results and social
share caches) breaks.

## Migration approach

1. Set up the Laravel 12 project (Phase 1), with `public/assets/*` etc. copied in unchanged first,
   before any Blade conversion — gives an early sanity check that raw asset serving works
   identically under Laravel's routing.
2. Build `layouts/app.blade.php` + the shared components (nav/header/footer/page-title/etc.) from
   `partials/head.php` + `partials/header.php` + `partials/footer.php` and
   `includes/components.php`, matching output markup/classes exactly (diff-check rendered HTML
   against the original where practical).
3. Convert each page one at a time (`index.php` first, since it's the highest-traffic page and
   exercises the most components), verifying visually against the live site after each.
4. Wire up the full legacy-redirect table above and test every row.
5. Only after the public site is verified working end-to-end does Phase 2 (auth/admin foundation)
   begin — the public migration and the certificate system are sequenced, not built in parallel,
   so there's always a working, comparable baseline.

## What is explicitly NOT changing in this migration

- Visual design, copy, IEEE brand-lock elements (see `CLAUDE.md`).
- `assets/css/style.css` and `assets/js/main.js` behavior (nav, countdown, reveal animations,
  lightbox, carousel, copy-to-clipboard, contact mailto form).
- The event-phase logic (early/regular/closed/live/ended) driving the alert banner, countdown, and
  registration CTAs — ported faithfully from `includes/components.php`'s `eventPhase()`.
- The contact "form" stays a client-side `mailto:` opener (no server-side mail sending exists
  today and none is required by this migration — if the user wants real server-side contact-form
  email in the future, that's a separate, explicitly scoped feature, not part of this migration).

## Old QR generator disposition

`reference/qr-generator/IEEEQRCODEGENERATOR-main/` (Flask prototype, archived from
`~/Downloads/Compressed/IEEEQRCODEGENERATOR-main.zip`) is kept as **read-only reference only**.
It is never run, never deployed, never imported as a dependency. Its business-logic concepts
(cryptographically random codeword, formula-injection-safe Excel export, warn-don't-block
duplicate philosophy) are reimplemented natively in Laravel per `docs/CERTIFICATE_SYSTEM.md`; its
file-based storage (Excel as the database, QR payload containing raw personal data) is explicitly
not carried forward — see `docs/PROJECT_REQUIREMENTS.md` and `CLAUDE.md`.

The original downloaded copy at `~/Downloads/Compressed/IEEEQRCODEGENERATOR-main.zip` and the
original site download this repository was built from are left untouched outside this workspace.
Nothing in this repository deletes or modifies files outside `D:\Projects\IEEE_WEB_and_Certificates`.
