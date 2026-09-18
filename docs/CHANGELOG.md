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

## Phase 4 — Certificate template design and field placement (2026-09-17)

- **Inspected the actual attached demo Canva PDF before deciding anything** (per the brief's
  explicit instruction): PDF 1.4, classic non-compressed xref table, single page, `MediaBox
  [0.0 8.579974 842.25 604.07996]` (≈A4 landscape, non-zero origin), Producer/Creator both Canva,
  1.3MB. The non-zero MediaBox origin directly shaped the coordinate-conversion approach below.
  Also confirmed Imagick is present in the local Sail dev image but **not** in production's
  confirmed PHP module list (Phase 0's inspection) — decisive against any server-side PDF
  rasterization for this phase.
- **Database**: one migration, `add_background_and_layout_columns_to_certificate_templates_table`,
  adding `original_filename`/`file_mime`/`file_size` (asset metadata for the existing
  `source_pdf_path` column) and two new nullable JSON columns, `certificate_number_layout` and
  `qr_code_layout` — the two system elements' storage (see design decision below). Verified
  rollback-safe.
- **Background handling**: the uploaded PDF is the only master asset — no server-side
  rasterization, no stored preview image. `TemplateBackgroundService` stores it under a
  server-generated UUID filename (never the client's), validated via `mimes:pdf` (real
  `fileinfo`-based content sniffing) plus an explicit `%PDF-` magic-byte check — no FPDI/Imagick
  dependency needed for validation either, since nothing parses the PDF's internal structure in
  this phase. Replacing a background doesn't touch existing field positions; the edit page warns
  that dimensions may have changed and to re-check the designer.
- **Coordinate system**: PDF points (bottom-left origin), matching Phase 0's original plan in
  `docs/TEMPLATE_EDITOR.md` — with one refinement found directly from inspecting the real demo
  PDF: conversion routes through **PDF.js's own `viewport.convertToPdfPoint()`/
  `convertToViewportPoint()`**, not a hand-rolled y-flip formula, because that demo PDF's MediaBox
  does not start at `[0,0]`. A naive formula would have silently misplaced every element on
  exactly this file.
- **System-element storage decision**: `certificate_number`/`qr_code` are explicitly *not*
  `template_fields` rows (per the brief) and *not* a new table — two nullable JSON columns directly
  on `certificate_templates` instead, since there are exactly two of these per template, always,
  with no independent lifecycle a table would justify. QR height is forced equal to width
  server-side on every save, regardless of what's submitted, guaranteeing "square by default" can't
  drift even from a hand-crafted request.
- **Services**: `TemplateBackgroundService` (upload/replace, safe delete-after-commit ordering) and
  `TemplateLayoutService` (one DB transaction per "Save Layout": page dimensions, every field's
  position/style, both system elements, plus a second IDOR check on field ownership independent of
  the Form Request's).
- **Authorization**: `CertificateTemplatePolicy::manageLayout()` — same two roles as template
  management generally, additionally `false` once a template is archived. Viewing the designer
  stays allowed for archived templates (reads the existing `update` ability); only the two write
  actions (save layout, upload background) check `manageLayout`.
- **Visual editor**: `GET/POST /admin/templates/{template}/designer`, a 3-column layout (available
  elements / PDF canvas / settings panel) rendered entirely with vanilla JS
  (`public/js/admin/template-designer.js`) — no Alpine/Vite introduced, matching the project's
  established admin-UI pattern (confirmed by inspecting `package.json`/`vite.config.js` first:
  Vite/Tailwind are unused default-scaffold leftovers, `@vite` appears in zero Blade views). Drag
  from the sidebar (HTML5 Drag and Drop) places a new element; Pointer Events handle subsequent
  move/resize on an already-placed element; a numeric settings panel (in PDF points, not pixels)
  offers precise entry as an alternative to dragging. Sample preview values (heuristic, driven by
  `field_type`/`field_key`, never stored) replace empty boxes so the layout is legible while
  editing; the QR sample is a real scannable code via `qrcode-generator` (same CDN version already
  used by the original site's HTA page — zero new dependency risk).
- **Dependencies**: zero new Composer packages (Phase 4 never opens/writes a PDF server-side).
  Two CDN-loaded JS libraries instead — `pdf.js` 3.11.174 and `qrcode-generator` 1.0.3 — both with
  an SRI hash independently verified by downloading the file and computing its own SHA-512, not
  copied blind. Full reasoning in `docs/ARCHITECTURE.md` §6.
- **Found and fixed live** (manual QA before/alongside writing tests, same discipline as Phases 2
  and 3): a Blade `@json()` directive whose argument was a multi-line `->map(fn ($f) => [...])`
  array literal failed to compile ("Unclosed '[' ... does not match ')'") — Blade's directive-
  argument parser doesn't handle a multi-line bracketed expression reliably. Fixed by precomputing
  the value in a `@php` block first and passing a single simple variable to `@json()`.
- **Manual QA** — the actual demo PDF, uploaded through the real HTTP flow (not a synthetic file):
  upload succeeded, byte-identical when streamed back (`sha256sum` match), designer page loaded
  and rendered `DESIGNER_CONFIG` correctly; a full "Save Layout" payload (two dynamic fields, the
  certificate-number element, the QR element, all at real coordinates against the demo PDF's
  842.25×604.08pt page) persisted correctly and reappeared identically on reload; a non-square QR
  submission (`width:80, height:200`) was silently corrected to `80×80` server-side; a position at
  `x: 99999` was rejected with "far outside the certificate canvas" and the field's stored position
  was untouched; a field ID belonging to a second, separately-created template was rejected with
  "do not belong to this template" and left unmodified — direct proof of the IDOR guard; archiving
  a template left the designer viewable (200, "Read-only" badge, `canEdit: false`) but blocked both
  the save-layout POST and the background-upload POST with 403; a non-PDF file (`.txt` renamed,
  and a real Laravel `UploadedFile::fake()` non-PDF in the automated tests) was rejected by both the
  `mimes:pdf` rule and the magic-byte check; both `certificate_manager` and `super_admin` dev
  accounts could do all of the above, confirming equal access. No headless-browser tool was
  available in this environment, so the actual drag/resize/pointer-event interactions and PDF.js's
  visual rendering were not exercised by an automated real browser — only via direct HTTP requests
  simulating what the client-side JS produces, plus Node.js syntax-checking
  (`node --check template-designer.js`) and careful code review of the PDF.js API usage. Flagged in
  the Phase 4 completion report as something worth a real manual browser pass before this ships.
- **Tests** (`tests/Feature/Admin/TemplateDesignerTest.php`): the 11 items requested — authorized
  open, unauthorized blocked, layout save + persistence (fields, styling), system-element
  persistence (both certificate_number and qr_code), QR-squareness enforcement, out-of-bounds
  rejection, cross-template field rejection (IDOR), archived-template block (both write actions),
  non-PDF rejection, valid-PDF upload + stream-back, and the Phase 3 blank-slug regression. 34
  tests pass total (23 prior + 11 new), 166 assertions, no regressions. `vendor/bin/pint --test`
  clean (79 files).
- **Docs**: `docs/CERTIFICATE_SYSTEM.md` gained a full §Certificate background & visual layout
  section (background handling, coordinate system, position/style JSON, system-element storage,
  sample preview data, status rules, server-side validation, the Canva-demo-PDF findings) and had
  several Phase-4-forward-references corrected now that the phase is done (including the PDF
  pipeline risk note, which now points at Phase 5 specifically). `docs/DATABASE_DESIGN.md` and
  `docs/ARCHITECTURE.md` also updated (new columns/files/dependency reasoning) per CLAUDE.md's
  documentation-upkeep rule, even though the brief only named CERTIFICATE_SYSTEM.md explicitly.
  Real certificate issuance, verification tokens, QR verification URLs, the public verification
  page, revocation, reissue, Excel bulk import, ZIP generation, and email sending were not started,
  per the brief's explicit stop condition.

## Phase 5 — Single certificate generation and issuance (2026-09-18)

- **Technical spike first, as required before any implementation**: installed `setasign/fpdi` +
  `tecnickcom/tcpdf` and imported the real demo Canva certificate (fetched from the location the
  user had saved it, since Phase 4's uploaded copy no longer existed after a later
  `migrate:fresh`/storage cleanup). `composer require` initially resolved `tcpdf:7.0.10`, whose own
  package description now reads "Deprecated legacy PDF engine... use tc-lib-pdf instead" — it threw
  a fatal error (`unable to read file: helvetica.json`) on the very first `new TCPDF()` call,
  because that release loads fonts through a separate, incompatible `tc-lib-pdf-font` package.
  Re-pinned to `tecnickcom/tcpdf: ^6.8` (classic, self-contained font system) — confirmed as the
  tested-compatible line by `setasign/fpdi`'s own `composer.json`, which pins its dev dependency to
  the same range.
- **Coordinate system, verified against the real PDF's raw bytes, not assumed**: the demo
  certificate's actual `/MediaBox` is `[0.0 8.579974 842.25 604.07996]` — confirming Phase 4's
  non-zero-origin finding, but also revealing the true page **height is 595.5pt**
  (`604.07996 - 8.579974`), not the `604.08` Phase 4's manual QA notes recorded (that number was
  the raw `ury`, never actually confirmed against a real PDF.js browser session — Phase 4 had no
  headless browser either). FPDI's `importPage()` normalizes the imported page so its own
  lower-left corner becomes local `(0,0)`; TCPDF's drawing API is top-left/y-down on top of that.
  Built one central conversion service, `App\Services\Certificates\Pdf\PdfCoordinateConverter`, and
  a standalone `PdfPageBoxReader` (using FPDI's public `PdfReader`/`PdfParser`/`StreamReader`
  classes, not the protected `Fpdi::getPdfReader()`) so real page dimensions can be read
  independently of building a full TCPDF document. `certificate_templates.page_width`/`page_height`
  (the Phase 4 designer's cached columns) are never trusted for rendering — proven necessary by the
  595.5-vs-604.08 discrepancy itself. Verified visually: text placed at raw `y=560` landed near the
  page top, text at raw `y=20` landed flush with the true bottom edge, a marker square landed
  exactly at its computed bottom-right position — all against the real demo PDF, rasterized with
  Ghostscript and visually inspected.
- **Font/Unicode strategy**: DejaVu Sans (bundled with TCPDF) renders Latin script including
  diacritics correctly out of the box. Bengali script rendered as empty "tofu" boxes with DejaVu
  Sans alone — fetched the open-source, OFL-licensed Noto Sans Bengali font from Google's font
  repository, pre-converted it once into TCPDF's embedded format (`TCPDF_FONTS::addTTFfont()`), and
  committed the output at `resources/fonts/tcpdf/` so no font conversion ever happens at request
  time or in production. `App\Services\Certificates\Pdf\CertificateFontResolver` picks one font per
  field value based on whether it contains Bengali codepoints. Documented limitation: TCPDF has no
  HarfBuzz-style shaping engine, so complex Bengali conjunct clusters aren't guaranteed correct
  ligature substitution — common names render correctly (verified), unusual ones should be
  spot-checked; not solved further now to avoid over-engineering a problem that hasn't actually
  occurred.
- **QR code**: no new package. TCPDF bundles native 2D barcode generation
  (`write2DBarcode('QRCODE,M', ...)`, real vector output) — `App\Services\Certificates\QrCodeService`
  builds the verification URL (`{APP_URL}/certificate/verify/{codeword}`, path configurable via
  `config/certificates.php`) and draws it directly. The public verification route/controller are
  Phase 6 work and do not exist yet; the URL shape is defined now purely so Phase 6 never has to
  touch or reprint an already-issued certificate.
- **Certificate number / verification token**: `CertificateNumberService` mints
  `IEEE-IUBAT-{year}-{6-digit sequence}` (prefix configurable) via a dedicated
  `certificate_number_counters` table (one row per year), incremented with `SELECT ... FOR UPDATE`
  inside the issuance transaction — race-condition safe by construction, not by retry-on-collision.
  For what the brief calls "verification token," reused the existing `certificates.codeword` column
  from the Phase 2 schema (already documented in CLAUDE.md as exactly this) rather than adding a
  duplicate column — `VerificationCodewordService` generates 32 CSPRNG bytes (`random_bytes()`,
  never `mt_rand()`), hex-encoded, retried up to 5 times against the DB unique constraint.
- **Snapshot strategy**: two new nullable JSON columns on `certificates` — `template_snapshot`
  (field definitions: label/key/type/options/is_recipient_name) and `layout_snapshot` (per-field
  position/style + system-element layouts + the page dimensions actually used at render time).
  Written once at issuance by `CertificateSnapshotService`, never touched again. Decided: the live
  template stays fully editable even after certificates exist against it (not locked down) — the
  snapshot is what makes historical certificates safe, not freezing the template. Verified by a
  test that edits a field's label/position after issuance and asserts the certificate's stored
  snapshot is unaffected.
- **Issuance pipeline** (`CertificateIssuanceService`): validates the template is active and has a
  background, copies the `is_recipient_name` field's value into `certificates.recipient_name`
  directly, mints the number + codeword, renders the PDF entirely in memory (no disk/DB writes
  possible on a render failure), then — inside one DB transaction — writes the PDF to
  `storage/app/private/certificates/{year}/{uuid}.pdf` and inserts the `certificates` row. A
  `catch` block deletes the just-written file if anything throws afterward (including exhausting
  codeword retries), so a failure never leaves an orphaned file or a DB row without a matching PDF.
  Deliberately simpler than a `status = generating` placeholder row — the flow is synchronous and
  fast, so there's no user-visible intermediate state to protect against.
  `CertificateStatus::GenerationFailed` stays in the enum for a possible future async path but
  nothing in this flow writes it.
- **Dynamic validation** (`IssueCertificateRequest`): rules built from the selected template's
  `template_fields` (required/type/dropdown-options), plus a `withValidator()->after()` pass that
  rejects any submitted field key not belonging to that template — catches both typos and a
  deliberately manipulated `fields[certificate_number]` payload (certificate numbers are never
  accepted from the client).
- **Admin UI**: `Admin\CertificateController` (`certificates.index`/`choose-template`/`create`/
  `store`/`show`/`download`) replaces the Phase-2 "coming soon" placeholder for Certificates.
  Issue form generated from the template's fields (same field-type switch pattern as Phase 3's Form
  Preview, but live). Detail page shows field values (via `template_snapshot`, so it stays correct
  even after later template edits), an inline PDF preview `<iframe>`, and a download link — both
  against the same policy-checked streaming route, never a public storage path. Verification
  codeword is never shown in the normal UI. Duplicate-submission protection: POST/Redirect/GET plus
  a client-side submit-button disable; no server-side idempotency token added on top (the brief
  said not to over-engineer this, and a double-POST just mints two distinct, both-valid
  certificates — not a data-corruption risk).
- **Security**: `App\Policies\CertificatePolicy` (auto-discovered, same two-role boundary as
  templates). Draft/archived templates rejected both in the controller and again inside
  `CertificateIssuanceService` (defense in depth). Dropdown values re-validated server-side against
  the template's actual configured options regardless of what the client submits. PDF
  download/preview routes are policy-checked, never a raw storage URL.
- **Tests** (`tests/Feature/Admin/CertificateIssuanceTest.php`, 15 new): authorized open, guest/
  inactive blocked, draft/archived rejected, full pipeline (number format, codeword length/
  uniqueness, PDF exists and starts with `%PDF-`, snapshots populated), required-field validation,
  invalid dropdown rejected, unknown field key rejected, certificate number cannot be
  client-supplied, numbers sequential and unique across two issuances, listing + search by number/
  recipient, detail+download authorization, issued certificate unaffected by a later template edit,
  PDF-generation failure leaves zero certificates and zero orphan files, long-text overflow
  detected and surfaced as a warning. Uses a real FPDI-importable PDF generated by TCPDF itself as
  the test fixture (Phase 4's hand-written minimal PDF wasn't parseable enough, since Phase 4 never
  actually imported it) plus `Storage::fake('local')` to isolate test file writes. 49 tests pass
  total (34 prior + 15 new), 238 assertions, no regressions. `vendor/bin/pint` clean (101 files; 2
  pre-existing style issues fixed in the newly generated font-definition file).
- **Manual QA**: full pipeline exercised against the real demo Canva PDF outside the automated
  suite — created a template, uploaded the real PDF as its background, configured five fields
  (including a Bengali-script field and a long-text field) plus certificate-number/QR layouts at
  real demo-PDF coordinates, activated it, and issued one real certificate via
  `CertificateIssuanceService`. Rasterized the generated PDF with Ghostscript and visually
  confirmed: every field at its configured position, long text wrapping correctly with no
  auto-shrink, the certificate number rendered, a structurally valid QR code (finder patterns
  correct) at its configured box, and legible Bengali glyphs (no tofu boxes). No headless-browser
  tool was available in this environment (same limitation as Phase 4), so the designer's real
  drag/resize/pointer-event interactions in an actual browser, and a real phone QR scan, remain
  open manual-QA items for the user before this ships — flagged explicitly in the Phase 5
  completion report.
- **Docs**: `docs/CERTIFICATE_SYSTEM.md` gained/updated §PDF generation pipeline (replacing the old
  "open question" framing), §Single certificate generation, §Certificate number generation,
  §Verification codeword generation, §Snapshot strategy, §QR code, and the template-lifecycle
  edit-after-issuance rule. `docs/DATABASE_DESIGN.md` updated with the new `certificates` columns
  and the new `certificate_number_counters` table. `docs/ARCHITECTURE.md` updated with the new
  services/controller/policy/views and the corrected dependency table (FPDI/TCPDF actually added,
  with the 6.x-vs-7.x pin reasoning; `endroid/qr-code` struck through as unneeded). Public
  verification, revocation, reissue, Excel bulk import, ZIP generation, and email sending were not
  started, per the brief's explicit stop condition.

## Phase 6 — Simplified QR workflow and historical Excel import (2026-09-18)

- **Scope pivot, not a rewrite**: paused the PDF designer and automatic PDF generation (Phases
  4-5) rather than continuing them — that code, its routes, and its tests all stay in the
  codebase and stay green. Built the actually-needed near-term production workflow instead:
  migrate the old local QR/codeword tool (dynamic form -> codeword -> QR -> manual Canva
  placement) into the website, plus importing the historical Excel files that tool already
  produced. See `docs/CERTIFICATE_SYSTEM.md` §Simplified QR workflow.
- **No schema migration needed**: `certificates.pdf_path`/`template_snapshot`/`layout_snapshot`
  were already `nullable()` from Phase 5 — inspected first per the brief's explicit instruction,
  confirmed already correct, so no migration was written. Both new certificate-creation paths
  (QR-only issuance, Excel import) simply leave all three columns `null`.
- **`SimpleCertificateService`**: a new, deliberately small service for the no-PDF issuance path —
  reuses `CertificateNumberService` and `VerificationCodewordService` unchanged from Phase 5, skips
  everything PDF-related entirely. Kept separate from `CertificateIssuanceService` rather than
  adding a "skip the PDF" flag to it, since the two flows share only those two sub-services.
- **QR as standalone PNG**: `QrCodeService` gained `pngBytes()` using TCPDF's own
  `TCPDF2DBarcode::getBarcodePngData()` — confirmed working standalone (no PDF document needed) in
  a spike before writing any surrounding code. Still zero new QR packages. Generated on demand per
  request (`GET /admin/certificates/{certificate}/qr.png`), never written to disk — deterministic
  from the verification URL, so there's nothing to persist or clean up.
- **Admin UI**: `Admin\CertificateQrController` (`/admin/certificates/generate-qr[/{template}]`)
  reuses Phase 5's `IssueCertificateRequest` verbatim for the dynamic form. The certificate detail
  page now shows an inline QR preview, a "Download QR PNG" link, a copyable verification link
  (Clipboard API), and an optional "Copy QR image" button (feature-detected, hidden where
  unsupported — PNG download is the guaranteed path). The PDF-preview `<iframe>`/download button
  are now conditional on `pdf_path` being set, so QR-only certificates don't show a broken PDF
  section. Nav sidebar restructured to the brief's exact priority list: Generate QR, All
  Certificates, Import Excel, Categories/Templates, with Bulk Generation/Batches demoted below a
  "More" heading.
- **Excel import package**: `phpoffice/phpspreadsheet` (direct dependency, not the
  `maatwebsite/excel` wrapper — this project only needs a synchronous read of a few hundred rows
  within one request, not queued export/import abstractions). No security advisories at install
  time.
- **Column mapping, not a fixed header assumption**: the importer never assumes an Excel file's
  headings match this system's field labels (the brief's own examples — "Speaker Name" ->
  Recipient Name, "Designation" -> Role, "University" -> Institution — make clear they routinely
  won't). A mapping screen lists every column with a sample value next to a dropdown of targets
  (every assignable field, Existing Codeword, Existing Certificate Number, Ignore column). A
  best-effort auto-guess pre-selects obvious matches (substring/exact-label matches only, no
  synonym dictionary) but every row stays admin-reviewable/overridable.
- **Design bug caught in manual testing before shipping**: the first version used a separate
  `_recipient_name` mapping pseudo-target, distinct from the recipient field's own `field_key`.
  Consequence: `validateMapping()`'s "every required field must be mapped" check looked for the
  recipient field's `field_key` among the submitted targets, but the admin had (correctly, per the
  UI) selected the special `_recipient_name` target instead — so a template's own recipient field
  could never satisfy its own required-mapping check. Fixed by removing the special target
  entirely and treating the recipient field as a normal mappable field (matching exactly how the
  live QR-generation form already handles it) — caught by manually running the full pipeline via
  `tinker` against a real generated `.xlsx` before writing the automated test suite, not by the
  tests themselves (they were written after the fix).
- **State across upload -> mapping -> preview -> confirm without a new database table**: the
  uploaded file is stored once as `storage/app/private/imports/{server-generated-uuid}.xlsx`.
  Every later step carries that UUID (validated with a strict UUID regex before ever touching the
  filesystem — never a client-supplied path) plus the chosen mapping through hidden form fields,
  and re-derives everything else fresh from the stored file each time — confirm never trusts what
  preview merely echoed back, it re-runs the identical validation from scratch.
- **Existing-codeword/certificate-number migration behavior**: a non-empty mapped value is checked
  against a permissive format pattern, then for uniqueness against both the database and every
  other row already seen in the same file, then **preserved verbatim** if it passes — never
  silently replaced. An empty cell means "generate a new one," identical to a row with no
  historical value at all. This is the whole point of the import path: QR codes already printed on
  paper years ago must keep verifying against the same codeword.
- **Partial import, explicitly**: the brief's own preview mockup (`Imported: 147 / Skipped: 3`)
  assumes it, and it's the correct behavior for genuinely messy historical data. Every invalid row
  is skipped and reported (row-numbered, matching the real Excel row an admin would see), the rest
  import normally. `CertificateImportService` processes each valid row in its own transaction, not
  one transaction for the whole file, for the same reason.
- **Tests** (`tests/Feature/Admin/CertificateQrGeneratorTest.php`, 8 new;
  `tests/Feature/Admin/CertificateExcelImportTest.php`, 15 new): authorized/unauthorized access for
  both workflows, dynamic field validation, recipient-name + data JSON storage, codeword
  generation/uniqueness, draft/archived templates blocked from QR generation, standalone QR PNG
  endpoint produces a real PNG, certificate detail page hides the PDF section when absent, Excel
  upload accepted/rejected, header parsing, required-mapping enforcement, preview counts, invalid
  dropdown rows rejected, import confirmation writes real rows, existing codeword preserved,
  duplicate codeword rejected (both against the database and within the same file), missing
  codeword generates a new one, existing certificate number preserved, duplicate certificate number
  rejected, and a test that imports fresh data alongside a pre-existing Phase 5 certificate and
  confirms the older row is completely untouched. 72 tests pass total (49 prior + 23 new), 327
  assertions, no regressions.
- **Pint**: clean, 114 files (3 pre-existing style issues auto-fixed in the new files).
- **Manual QA**: full pipeline exercised via `tinker` against real service calls before the
  automated tests were written — a QR-only certificate issued end to end (number/codeword/PNG all
  verified valid), and a realistic 5-row `.xlsx` (missing name, duplicate codeword, valid rows)
  imported end to end with correct valid/invalid counts and correct preserved-vs-generated
  codeword counts. The full HTTP stack (routes, controllers, middleware, CSRF, views) is
  additionally exercised by the automated feature tests themselves. No real-browser pass was done
  (same limitation noted in Phases 4-5 — no headless-browser tool is available in this
  environment); the mapping screen's dropdowns and the Clipboard-API buttons have not been
  clicked through in an actual browser.
- **Docs**: `docs/CERTIFICATE_SYSTEM.md` gained §Simplified QR workflow (rationale, QR PNG
  generation, Excel import, column mapping, row validation, import result, security) and a
  clarifying note distinguishing it from the still-future §Bulk (Excel) generation. `docs/
  DATABASE_DESIGN.md` and `docs/ARCHITECTURE.md` updated (no-migration-needed note, new services/
  controllers/views, the `phpoffice/phpspreadsheet` dependency entry). The PDF designer, automatic
  certificate generation, public verification page, revocation, reissue, and Certificate Studio
  desktop software were not touched, per the brief's explicit stop condition.

## Phase 6 (continued) — Public certificate verification (2026-09-18)

- **Route**: `GET /certificate/verify/{codeword}` (name `certificate.verify`, `routes/web.php`,
  public — no auth, not under the `admin.` prefix). Constrained with
  `VerificationCodewordService::ACCEPTED_PATTERN` (`[A-Za-z0-9_-]{4,128}`) — inspected the real
  generated-codeword format (64 lowercase hex from `bin2hex(random_bytes(32))`) and the Excel
  importer's already-permissive historical-codeword format check before choosing this, so the
  route can never reject a legitimately preserved historical codeword. The upper bound (128) makes
  an absurdly long junk URL 404 at the routing layer, before any query or render.
  `throttle:60,1` (60 req/min/IP) deters enumeration without a CAPTCHA.
- **`CertificateVerificationService`**: the one place the lookup happens — exact
  `Certificate::where('codeword', $codeword)->first()`, never certificate ID, certificate number,
  or fuzzy matching. Returns a `VerificationResult` DTO, never the `Certificate` model — the view
  receives only certificate-number/recipient-name/template-name/issued-date/public-fields (and
  only what each outcome needs), so `codeword`/`id`/`created_by`/`pdf_path`/the raw
  `template_snapshot`/`layout_snapshot`/`data` JSON are structurally unreachable from the public
  Blade template, not merely "not currently rendered."
- **Status rules**: `Active` → Verified. `Revoked` → a separate response (certificate number only,
  no recipient/dynamic data, no `revocation_reason`) — the column/enum already existed from
  Phase 2, this display logic is new. Anything else (`Reissued`, `GenerationFailed`, or a future
  status) → the same "Certificate Not Verified" response an unknown codeword gets, so a
  reissued-and-superseded certificate's old codeword never keeps claiming to be valid. Verified
  first that `SimpleCertificateService` and `CertificateImportService` both already set
  `status: Active` on every row they create — no consistency bug found there.
- **Public field visibility, in order**: (1) `certificates.template_snapshot` when present (Phase 5
  PDF certificates), frozen at issuance; (2) the live template's current fields for Phase 6
  QR-only/Excel-imported certificates (both always have a `null` snapshot, since no PDF was ever
  rendered to snapshot anything from); (3) nothing beyond the base fields if neither resolves.
  Documented in full in `docs/CERTIFICATE_SYSTEM.md` §Snapshot/current-template fallback logic.
- **Bug found and fixed**: `CertificateSnapshotService::templateSnapshot()` never captured
  `show_on_verification`/`verification_label` — a real gap, since without it a Phase 5 PDF
  certificate's snapshot had no visibility data to read at all. Fixed; a snapshot written before
  this fix (missing the key entirely) is treated as `show_on_verification = false`, the safer
  default, never assumed `true`.
- **Bug found and fixed (caught in manual curl testing, before automated tests were written)**: the
  recipient-flagged field was rendering twice — once via the dedicated "Recipient" row, again as
  its own dynamic field row, whenever its `show_on_verification` also happened to be `true` (a
  common, expected configuration). Fixed by excluding the recipient field from the dynamic-field
  list in both the snapshot and live-template resolution branches.
- **One URL-building implementation, not three**: `QrCodeService::verificationUrlFor()` now builds
  the URL via Laravel's `route('certificate.verify', [...])` instead of string-concatenating
  `config('app.url')` with a path template. Every caller — the in-PDF QR, the standalone PNG, the
  admin detail page's copy-link/"View Public Verification" buttons — already went through this one
  method, so fixing it here fixed it everywhere at once. Removed the now-redundant
  `config('certificates.verification_url_path')` key and its `CERTIFICATE_VERIFICATION_URL_PATH`
  env var, since the real route makes both dead weight.
- **Page**: `resources/views/verify/show.blade.php` — one standalone Blade file (not
  `x-layouts.app`, not the admin layout), reusing the public site's CSS variables/fonts for brand
  consistency without pulling in the full header/nav/footer chrome, which would be noise on a page
  almost everyone reaches by scanning a QR code on a phone. No JavaScript required. One `@switch`
  on `VerificationOutcome` renders all three states.
- **Privacy/anti-caching headers**: `X-Robots-Tag: noindex, nofollow` + a matching `<meta>` tag
  (these URLs are per-certificate secrets, never meant to be indexed) and `Cache-Control: no-store`
  (a status shown here, e.g. Verified, can change later via revocation — never let a browser/proxy
  cache a stale result).
- **Admin UI**: certificate detail page gained a "View Public Verification" link next to the
  existing "Copy link"/"Download QR PNG" buttons from earlier in Phase 6.
- **Tests** (`tests/Feature/PublicVerificationTest.php`, 15 new): no-login-required, all three
  certificate-creation paths (manual QR, Excel-imported with a preserved historical codeword,
  Phase 5 PDF-path via a realistic snapshot) verify correctly, correct certificate
  number/recipient/public-field display, hidden fields and raw JSON never appear, codeword itself
  never appears in page content, unknown/very-long/malformed codewords handled safely (the last two
  404 at the route layer), revoked and reissued statuses both correctly fail to show "Verified",
  the QR service's own URL output actually resolves to a Verified page, noindex/no-store headers
  present, and the route's throttle middleware is actually registered. One test assertion was
  itself flawed during development (checking the page doesn't contain the certificate's raw
  numeric ID as a substring — false-failed because "2026" in the issued date coincidentally
  contains the digit) and was corrected to test something meaningful instead of loosened to pass.
  87 tests pass total (72 prior + 15 new), 372 assertions, no regressions.
- **Pint**: clean, 120 files.
- **Manual QA**: full pipeline exercised via real HTTP requests (curl) against a live local server
  before and after each bug fix above — a QR-only certificate's verification page, a revoked
  certificate, an unknown codeword, and a route-rejected 500-character junk codeword were all
  fetched and their exact rendered HTML/headers inspected. No real phone/browser QR-scan pass was
  performed (no such tooling is available in this environment) — that remains for the user's own
  manual QA pass per the brief's §19.
- **Docs**: `docs/CERTIFICATE_SYSTEM.md`'s old speculative "Verification page" section (which
  described a `/verify/{codeword}` path that never shipped, a `verification_logs` table that still
  doesn't exist, and treated `reissued` as displayable-as-historical rather than Not Verified) was
  replaced with §Public verification describing what was actually built, plus a new
  §Snapshot/current-template fallback logic subsection. §Revocation and §Reissue updated to
  reflect that the display logic now exists even though the admin-facing action still doesn't.
  `docs/ARCHITECTURE.md` updated (new `VerificationController`/`Verification/` service files, the
  corrected data-flow diagram's Verification block, dependency-table note). `docs/DATABASE_DESIGN.md`
  updated (no new table needed; `CertificateStatus::Revoked` now actually consumed).

## Phase 6 (rebuilt) — Simple QR tool, based on the real reference tool (2026-09-18)

- **Why this is a rebuild, not an extension**: the earlier "Simplified QR workflow" (the two
  entries above) reused `certificate_templates`/`certificates` and required an advanced
  `CertificateTemplate` to exist before Generate QR or Import Excel would work at all — exactly the
  dependency the user flagged as wrong ("No active templates. Activate a template first.",
  "No templates exist yet."). The real reference tool
  (`IEEEQRCODEGENERATOR-main/app.py` + `templates/index.html`) was inspected in full before writing
  any code, per explicit instruction not to rely on earlier assumptions — several real findings
  contradicted the earlier design (no "Other" role option exists anywhere in the real tool; the old
  QR encodes plain text, not a URL; there's one fixed form/category, not several; Excel is split
  one file per conference+role combination with the exact headings SL/Conference/Role/Name/Session/
  Codeword/Created At/QR File). See `docs/CERTIFICATE_SYSTEM.md` §Simple QR tool for the full
  inspection notes and §Known differences for every deliberate deviation, documented rather than
  silently diverging.
- **Deleted, not kept as dead code**: the incorrectly-coupled `CertificateQrController`,
  `CertificateImportController`, `SimpleCertificateService`, and the advanced system's
  `CertificateImportService`/`CertificateImportValidator`/`ImportMappingTarget`/`ImportRowResult`/
  `ImportValidationResult`/`ImportSummary` (all built specifically for those two now-removed
  controllers, with no other caller) were removed outright, along with their views and their two
  test files (`CertificateQrGeneratorTest.php`, `CertificateExcelImportTest.php`). The genuinely
  generic `ExcelFileReader` was kept and is now shared by both importers. The Phase 5 PDF-issuance
  flow (`CertificateController`, `CertificateIssuanceService`, its routes/views/tests) was not
  touched.
- **New, fully independent schema**: `qr_categories`, `qr_category_fields`, `qr_certificates` — no
  foreign key to `certificate_templates`/`certificates` in either direction. Proven independence,
  not just claimed: `tests/Feature/Admin/QrToolGenerateTest.php` and `QrToolImportTest.php` both
  run against a database with zero `certificate_templates` rows.
- **Codeword format matches the real old tool exactly**: 16 characters, uppercase A-Z/0-9, via
  `App\Services\QrTool\QrToolCodewordService` using PHP's CSPRNG (`random_int()`, never
  `mt_rand()`) — reproducing the *shape* of `secrets.choice(string.ascii_uppercase +
  string.digits)` × 16 from the real `app.py`, not converting/reusing any of its actual output. A
  shared constant, `VerificationCodewordService::ACCEPTED_PATTERN`
  (`[A-Za-z0-9_-]{4,128}`), already covered this format without any change — confirmed by
  inspecting the exact generated format and the existing Excel-import format check before touching
  the route constraint, per explicit instruction.
- **QR content is intentionally different from the old tool**: the real tool encodes human-readable
  text with no verification step at all (`qr_payload()` builds a multi-line label string); the
  Laravel version encodes the public verification URL instead, per the brief's explicit
  instruction. Documented as the single largest intentional behavior change, not a silent
  divergence.
- **Default category matches the real old tool exactly**: `Database\Seeders\QrCategorySeeder`
  (runs in every environment, unlike the local/testing-only `AdminUserSeeder`) creates
  "BECITHCON 2026" with the real `PRESET_ROLES` (`Session Chair`/`Invited Speaker`/`Keynote
  Speaker`/`Volunteer` — no "Other", because the real tool has none) and an optional Session field,
  matching the real form's optionality.
- **Generate QR / Records / Import Excel / QR Categories** — new admin section
  (`Admin\QrTool\*` controllers, `/admin/qr-tool/*` routes), structured exactly per the brief's nav
  suggestion, with the advanced system's own nav items demoted under a separate "Advanced / Future"
  heading. Import reuses the exact old-tool headings (SL and QR File always ignored — SL is a
  per-file row counter with no portable meaning, QR File is a local filesystem path that's never
  imported since the QR is regenerated from the preserved codeword instead) with auto-guessed
  column mapping and the same preserve-if-valid-and-unique-else-generate rule for codewords as the
  advanced importer, plus an additional "Original Created Date" mapping target to preserve a
  historical row's real registration timestamp.
- **Bug found and fixed during manual `tinker` testing, before writing the new mapping/validator
  code**: an early draft copied the advanced importer's ORIGINAL (already-fixed-once) mistake of
  using a separate "Recipient Name" mapping pseudo-target distinct from the recipient field's own
  `key` — caught immediately this time, since the exact same class of bug had already been
  diagnosed and fixed once in the advanced system's importer (see the first Phase 6 entry above);
  fixed the same way, by treating the recipient field as a normal mappable field.
- **Public verification serves both sources**: `CertificateVerificationService::verify()` now
  checks `QrCertificate` first, then `Certificate`, returning one shared `VerificationResult` DTO
  either way (extended with a nullable `eventName` for simple records and a now-nullable
  `certificateNumber`/`templateName` for cases where a source doesn't have one). `QrCodeService`
  gained `verificationUrlForCodeword()` as the one shared URL-building primitive both
  `verificationUrlFor(Certificate)` and the simple QR tool now call — still exactly one
  implementation, never three.
- **Tests**: `tests/Feature/Admin/QrToolGenerateTest.php` (11) and `QrToolImportTest.php` (8) are
  new; `tests/Feature/PublicVerificationTest.php` was rewritten to use real `QrCertificate`
  fixtures (via `QrCertificateIssuanceService`/`QrCategoryImportService`) instead of the deleted
  `SimpleCertificateService`, gaining one additional test (a revoked simple QR record). Net: 84
  tests pass (65 after the deletions + 19 new), 391 assertions, confirmed from a fresh migration +
  seed. No "Other"-role test was written — the real tool has no such behavior to test.
- **Pint**: clean, 145 files.
- **Manual QA**: exercised directly via `tinker` + `curl` against a live local server before writing
  the automated tests — created the seeded BECITHCON 2026 category, confirmed zero
  `certificate_templates` rows, generated a real QR certificate end to end (16-char codeword format
  confirmed by regex), and confirmed its public verification page shows exactly the brief's
  specified layout (no Certificate Number row, Conference/Event shown, Role/Session shown, no
  duplicate recipient row). No real browser/phone QR-scan pass was performed — see the completion
  report for what remains for the user's own manual QA.
- **Docs**: `docs/CERTIFICATE_SYSTEM.md`'s "Simplified QR workflow" section was replaced outright
  (not just amended) with "Simple QR tool", including the full old-tool inspection notes, the
  architecture table, codeword-format reasoning, and a "Known differences from the old tool"
  section. §Public verification, §QR code, and §Snapshot/current-template fallback logic updated
  for the dual-source lookup. `docs/ARCHITECTURE.md` and `docs/DATABASE_DESIGN.md` updated with the
  new independent schema/services/controllers/views and corrected stale references to the deleted
  classes.
