# Changelog

Entries are added after each phase (or any significant change), newest first. This is a
project-behavior changelog, not a raw git log — explain what changed and why in plain English.

## Phase 0 — Repository audit and documentation (2026-09-17)

- Inspected the entire existing plain-PHP public website (`index.php`, `about.php`,
  `committee.php`, `contact.php`, `events.php`, `membership.php`, `becithcon-2026.php`,
  `hta-2026.php`, `includes/`, `partials/`, `assets/`, `.htaccess`, `robots.txt`, `sitemap.xml`,
  `site.webmanifest`). Documented full current + legacy URL map in `docs/MIGRATION_PLAN.md`.
- Located the old QR-generator prototype, which was not in the project workspace as expected — it
  was found at `~/Downloads/Compressed/IEEEQRCODEGENERATOR-main.zip`. Extracted a read-only
  reference copy into `reference/qr-generator/IEEEQRCODEGENERATOR-main/` inside this repository so
  it's inspectable and versioned going forward, without touching the original download. Fully
  read and documented its architecture (Flask + openpyxl, per-conference-per-role Excel files,
  `secrets.choice()`-based 16-char codeword, exact-match duplicate check, QR payload containing
  raw certificate data) in `docs/CERTIFICATE_SYSTEM.md` and the Phase 0 report.
- Identified a real architectural risk before committing to the PDF pipeline: FPDI's open-source
  edition only imports PDFs up to version 1.4, while Canva exports are typically newer; no
  Imagick/Ghostscript is available in the hosting's PHP module list as a rasterization fallback.
  Flagged as an open decision in `docs/CERTIFICATE_SYSTEM.md` — needs testing against a real
  Canva-exported certificate PDF before Phase 4.
- Noted a local-vs-production PHP version mismatch (local CLI 8.5.8 vs. production 8.2.31) as a
  development-environment risk; resolution approach to be confirmed with the user.
- Created the full documentation set: `CLAUDE.md`, `docs/ARCHITECTURE.md`,
  `docs/PROJECT_REQUIREMENTS.md`, `docs/DATABASE_DESIGN.md`, `docs/CERTIFICATE_SYSTEM.md`,
  `docs/TEMPLATE_EDITOR.md`, `docs/SECURITY.md`, `docs/TESTING.md`, `docs/DEPLOYMENT_CPANEL.md`,
  `docs/MIGRATION_PLAN.md`, this file.
- No Laravel code written yet. No production/live systems touched. The original site files at the
  repository root and the original QR-generator download outside the repository are untouched.

## Phase 1 — Laravel foundation and public site migration (2026-09-17)

- Scaffolded Laravel 12.69.2 with Laravel Sail, running PHP 8.2.33 in Docker to match production
  exactly (see `docs/ARCHITECTURE.md` §6a). Fixed a Windows/Docker-Desktop-specific permissions
  issue where `storage/`/`bootstrap/cache/` were unwritable by the container's `sail` user
  (documented in `README.md`).
- Ported the entire public site from plain PHP to Blade, preserving markup, CSS classes, JS
  behavior and content as closely as possible:
  - `includes/config.php` → `config/site.php` (same data shape) +
    `App\Services\SiteContentService` (computed helpers: `eventPhase()`, `fmtDay()`,
    `headerAlert()`, `navCta()`, `eventJsonLd()`, etc. — direct ports of the original functions).
  - `includes/components.php`'s markup helpers → Blade components under
    `resources/views/components/site/` (`page-head`, `section-head`, `icon-card`, `person-card`,
    `cta-block`, `callout`, `stat-card`, `crumb`, `header`, `footer`) plus an `@icon()` Blade
    directive replacing the `svg()` icon-sprite helper.
  - `partials/head.php` + `partials/header.php` + `partials/footer.php` →
    `resources/views/components/layouts/app.blade.php` (a Blade layout component).
  - All 8 pages (`index`, `about`, `committee`, `contact`, `events`, `membership`,
    `becithcon-2026`, `hta-2026`) converted to `resources/views/pages/**`, served by
    `PageController` and `EventController`.
  - `assets/`, `robots.txt`, `sitemap.xml`, `site.webmanifest` copied verbatim into `public/`.
- Registered every canonical URL plus the complete legacy-redirect table from
  `docs/MIGRATION_PLAN.md` in `routes/web.php` (301s for old `.html`/`.php` URLs and all three
  historical HTA-page slugs).
- **Verified by running the actual original plain-PHP site side by side** (via PHP's built-in
  server against `reference/legacy-site/`, inside the same PHP 8.2 container) and diffing the
  rendered, tag-stripped text content of every page against the new Blade output. Found and fixed
  three real bugs this way: `becithcon.date_label`, one BECITHCON track title, and a homepage date
  line were being double-HTML-escaped (`&amp;ndash;` instead of `&ndash;`) because they'd been
  written with Blade's escaping `{{ }}` instead of raw `{!! !!}` output — config-sourced strings
  that intentionally contain HTML entities need raw output, same as the original's documented "raw
  echo intentionally" convention. After the fix, all 8 pages produce **identical text content** to
  the original (only difference remaining: the original ships raw HTML `<!-- -->` developer
  comments in its response bytes, which Blade's `{{-- --}}` comments compile away entirely —
  invisible in a rendered browser either way).
  Also restored `header_remove('X-Powered-By')`, present in the original but initially dropped.
- Added `tests/Feature/PublicSiteTest.php`: every canonical URL renders page-specific content,
  every legacy URL 301-redirects correctly, IEEE brand-lock elements are present, and the carried-
  over static assets exist at their original paths. Deliberately minimal per the instruction not to
  over-invest in tests for static content — the page-by-page content diff above was the primary
  verification method, this is a regression guard, not the main check.
- Known minor caveat: `/index.php` (only that one exact legacy URL) returns 200 directly rather
  than a visible 301, due to how Symfony resolves Laravel's own front-controller path — documented
  in `docs/MIGRATION_PLAN.md`. Not a functional break and not an indexed URL.
- Certificate system, admin, and auth are explicitly out of scope for this phase per the user's
  instruction — Phase 2 starts from here.
