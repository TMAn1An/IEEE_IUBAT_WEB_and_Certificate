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

## Phase 2 — Database + authentication/admin foundation (2026-09-17)

- **Database**: 5 migrations — `add_role_and_status_to_users_table` (adds `role`, `is_active` to
  Laravel's default `users` table, as a separate migration rather than editing the historical one),
  plus new tables `certificate_templates`, `template_fields`, `certificate_batches`, `certificates`.
  Kept deliberately lean per the brief — no `verification_logs`/`audit_logs` yet, no Excel/ZIP/PDF
  path columns until the phases that populate them exist. Verified rollback-safe
  (`migrate:rollback --step=5` then `migrate` cleanly). Full column-by-column detail in
  `docs/DATABASE_DESIGN.md`.
- **Enums** (`App\Enums\*`): `UserRole`, `CertificateTemplateStatus`, `TemplateFieldType`,
  `CertificateBatchStatus`, `CertificateStatus` — plain `string` DB columns, cast to PHP backed
  enums at the model layer (Laravel's current recommended approach over native `ENUM` columns).
- **Models**: `User` (updated with `role`/`is_active` casts and `createdTemplates()` /
  `createdBatches()` / `createdCertificates()` relationships), `CertificateTemplate`,
  `TemplateField`, `CertificateBatch`, `Certificate` — all relationships from the brief
  (`fields()`, `certificates()`, `batches()`, `creator()`, `template()`, `batch()`,
  `reissuedFrom()`/`reissuedTo()`) with `array` casts on every JSON column (`data`, `options`,
  `position`, `style`).
- **Authentication**: hand-rolled `Admin\AuthController` (`Auth::attempt()` + session guard) — no
  Breeze/Fortify/Jetstream, matching the brief's "avoid unnecessarily heavy packages." Login
  throttling via `RateLimiter` (5 attempts/60s, keyed on email+IP). No public registration route
  exists. `EnsureUserIsActive` middleware (alias `active`) blocks a deactivated account on its very
  next request, not just at the next login attempt.
- **Authorization**: `App\Policies\UserPolicy` (Laravel auto-discovery, no manual registration
  needed) gates all user-management actions to Super Admin, checked server-side via
  `$this->authorize()` in `Admin\UserController` — confirmed live (not just by reading the code)
  that a Certificate Manager gets a real 403 on `/admin/users*`, not a hidden nav link. The admin
  sidebar additionally hides the Users link for non-Super-Admins as a UX nicety on top of that.
- **Last-active-Super-Admin protection**: `UserController::isLastActiveSuperAdmin()` blocks both
  deactivating that account and changing its role away from `super_admin`, checked before either
  action commits. There's no user-deletion feature in Version 1 (deactivate only), so the
  "accidentally delete the last admin" failure mode doesn't exist to begin with.
- **Admin UI**: a plain-CSS (`public/css/admin.css`, no build step — no JS interactivity needed yet)
  layout with a sidebar (Dashboard, Templates, Certificates, Bulk Generation, Batches, Users — the
  last one hidden for non-Super-Admins). Dashboard shows live counts from the new tables (all zero
  until later phases populate them). Templates/Certificates/Bulk Generation/Batches are honest
  "not built yet, planned for Phase N" pages (`Admin\ComingSoonController`, one controller/view for
  all four) rather than dead links or fake functionality. Users has real CRUD: list, create,
  edit (name/email/role/password), activate/deactivate — no delete.
- **`php artisan app:make-admin`**: interactive console command (name/email/role prompted, password
  via `$this->secret()` so it's never in shell history) for creating the first production Super
  Admin without a password ever touching source control, a seeder, or a committed file — the
  command `docs/DEPLOYMENT_CPANEL.md` already referenced now actually exists.
- **Dev seeding**: `AdminUserSeeder` creates two local-only accounts (a Super Admin and a
  Certificate Manager) from `SEED_ADMIN_EMAIL`/`SEED_ADMIN_PASSWORD`/`SEED_MANAGER_EMAIL`/
  `SEED_MANAGER_PASSWORD` env vars, falling back to clearly-fake `*.test` defaults — and refuses to
  run outside `local`/`testing` environments, so it can never seed a known password into a real one.
- **Found and fixed while verifying live** (not just from reading the code): the base
  `App\Http\Controllers\Controller` in Laravel 12's slim skeleton no longer includes
  `AuthorizesRequests`, so `$this->authorize()` was a fatal error until the trait was added back —
  caught by manually exercising `/admin/users` as both roles, not by the test suite (written after).
- **Tests** (`tests/Feature/Admin/AuthTest.php`, `UserManagementTest.php`,
  `Feature/DatabaseSchemaTest.php`): the 7 items requested — guest blocked, active admin logs in,
  inactive admin blocked (both at login and mid-session), Certificate Manager blocked from user
  management, Super Admin allowed, last-active-Super-Admin protected (both deactivation and role
  change), migrations verified via an explicit schema-columns check. Kept to exactly this list, no
  broader CRUD suite. All 16 tests pass (12 new + the 4 from Phase 1, re-run to confirm no
  regression) — `php artisan test`, 106 assertions.
- **Docs updated**: `docs/DATABASE_DESIGN.md` (rewritten to match the actual implemented schema,
  with a "not built yet" section for what later phases add), `docs/ARCHITECTURE.md` (folder layout,
  the Vite-vs-plain-CSS decision for Phase 2's admin UI, the no-auth-package decision, corrected the
  testing-framework row from the originally planned Pest to the PHPUnit actually in use),
  `docs/SECURITY.md` (§Authentication & authorization rewritten to describe what's implemented and
  verified, rate-limit numbers now decided). `CLAUDE.md` needed no changes — its rules already
  anticipated this implementation. Certificate template/generation/QR/Excel functionality
  intentionally not started beyond the database/model foundation, per the brief.

## Phase 3 — Certificate template management + dynamic fields (2026-09-17)

- **Database**: one migration, `add_is_recipient_name_to_template_fields_table` (boolean, default
  `false`). Verified rollback-safe. No other schema changes — everything else builds on the
  Phase 2 `certificate_templates`/`template_fields` tables as-is.
- **Enums**: `TemplateFieldType::assignable()` / `isAssignable()` — the only types an admin can pick
  in Phase 3 (`text`, `long_text`, `number`, `date`, `dropdown`); `certificate_number` and `qr_code`
  are excluded and documented as system-managed layout elements, never ordinary input fields (see
  `docs/CERTIFICATE_SYSTEM.md` §System fields vs. input fields — this is the distinction the brief
  asked to be prepared for Phase 4, without building the PDF-placement side of it yet).
  `CertificateTemplateStatus::badgeClass()` for the admin UI's status badges.
- **Services** (`app/Services/Templates/`): `TemplateService` (unique slug generation from the
  name; `activationErrors()`/`activate()` — the 5-rule draft→active gate the brief specified, kept
  deliberately separate from any PDF/"generation ready" concept, which is Phase 4/5's job, not
  this one's) and `TemplateFieldService` (field-key format + reserved-word validation as a static
  helper reused by both Form Requests and the activation gate; recipient-field exclusivity —
  setting one field's flag transactionally clears any other on the same template; Move Up/Move
  Down as a plain sort_order swap with the adjacent row, not drag/drop). Controllers stay thin —
  one service call each, per CLAUDE.md.
- **Authorization**: `CertificateTemplatePolicy` (auto-discovered), explicit and permissive for
  both admin roles per the brief ("both super_admin and certificate_manager may manage
  templates") — written as a real policy rather than relying on "only two roles exist right now"
  so a future, more restricted third role wouldn't silently inherit template access.
- **Admin UI**: Templates index/create + a single edit page that doubles as the template's detail/
  management screen (metadata form, field table with Recipient/Required/Public-Verification
  badges and Edit/Remove/Move-Up/Move-Down actions, an "Add field" link, and a live Form Preview
  rendering disabled inputs straight from `template_fields`) — no separate `show` route, matching
  the pattern already used for Users. Field create/edit share one Blade partial
  (`fields/_form.blade.php`). Only new JS: vanilla add/remove for dropdown-option inputs, exactly
  as scoped — no Alpine/Vite introduced.
- **Found and fixed live** (manually exercising both sample templates end-to-end before writing
  automated tests, same discipline as Phase 2): `TemplateController::store()` read
  `$data['slug']` unconditionally, which is only a valid array key when the request actually
  included a `slug` field — submitting the create form with slug left blank (the documented,
  expected way to get an auto-generated slug) fataled with "Undefined array key". Fixed to
  `$data['slug'] ?? null`.
- **Manual QA — the Phase 3 acceptance test** (see docs/PROJECT_REQUIREMENTS.md's acceptance
  test for the project-wide version this is a slice of): built both sample templates end-to-end
  through the admin UI via real HTTP requests (cookies, CSRF tokens), zero code changes between
  them —
  - **Template A — BECITHCON Speaker**: `name` (text, recipient), `role` (dropdown: Keynote
    Speaker/Invited Speaker/Session Chair), `institution` (text). Activated successfully.
  - **Template B — Research Paper Certificate**: `author_name` (text, recipient), `paper_title`
    (long_text), `paper_id` (number), `track` (dropdown). Activated successfully.
  - Verified live: dropdown options render correctly in both the field table and the Form Preview;
    reordering (`author_name` moved above `paper_title`) persisted correctly; the Form Preview
    rendered visibly different forms for the two templates (a `<select>` with BECITHCON's three
    role options vs. a `<textarea>` for the paper's long_text field) — the concrete proof the
    dynamic architecture works, not just an assumption from reading the code; activation correctly
    blocked a zero-field template, then a field-but-no-recipient template, both with the specific
    listed reason shown in the UI, and succeeded once fixed; setting `is_recipient_name` on a
    `long_text` field was rejected ("Only a text field may be the recipient name field"); setting
    a new recipient field automatically un-set the previous one (no error, no manual unset step);
    a dropdown submitted with zero options was rejected and never persisted a broken field row; a
    duplicate `field_key` within a template was rejected; `field_key=certificate_number` was
    rejected as reserved; deleting a field actually removed it; archiving a template flipped its
    status without touching its fields; a guest and a deactivated admin were both redirected to
    login on `/admin/templates`; a Certificate Manager (not just a Super Admin) could do all of
    the above, confirming both roles have equal template access as specified.
- **Tests** (`tests/Feature/Admin/TemplateManagementTest.php`): the 7 items requested — template
  creation, field-key uniqueness, dropdown-requires-options, single-recipient-field enforcement,
  activation gate (both the failure and the subsequent success once fixed, in one test), the Form
  Preview rendering fields pulled from the database, and guest/deactivated-admin access blocked.
  Added `App\Models\CertificateTemplate`/`TemplateField` factories (didn't exist before Phase 3
  needed them for tests). Extended `DatabaseSchemaTest` with the new `is_recipient_name` column.
  All 23 tests pass (16 from Phase 1/2 + 7 new), 130 assertions, no regressions.
- **Docs updated**: `docs/CERTIFICATE_SYSTEM.md` (new §Dynamic field architecture section — field
  types, the system-fields-vs-input-fields distinction, the recipient-name concept, field-key
  rules, and the full activation-validation rule list; §Template lifecycle rewritten to mark what's
  built vs. still planned), `docs/DATABASE_DESIGN.md` (`is_recipient_name` column documented,
  "Not built yet" list updated to distinguish "the flag exists and is enforced" from "certificate
  generation actually reads it," status line bumped to Phase 3), `docs/ARCHITECTURE.md` (folder
  layout: new controllers/requests/policy/services/views). PDF upload, template positioning,
  drag/drop, QR, certificate generation, and Excel are explicitly out of scope for this phase per
  the brief and were not started.
