# Architecture

## 1. Overview

One Laravel 12 application serving two audiences:

1. **Public website** — the existing IEEE IUBAT Student Branch site, migrated to Blade with
   preserved design/URLs/content.
2. **Certificate system** — admin-only, behind auth: template management, single + bulk
   certificate generation, and a public `/verify/{codeword}` endpoint that anyone (including an
   anonymous QR scan) can hit.

Both share one codebase, one database, one deployment, because that's what fits on cPanel shared
hosting without extra moving parts (no separate services, no Docker, no Node server at runtime).

## 2. Folder layout

**Status**: reflects what's actually built through Phase 2. Items still marked as planned (not yet
created) are for Phase 3 onward — see `docs/CHANGELOG.md` for what landed in which phase.

```
app/
  Http/
    Controllers/
      PageController.php           # public: home/about/committee/contact/events/membership
      EventController.php          # public: becithcon-2026, hta-2026
      Admin/
        AuthController.php         # login/logout only — no registration (Phase 2)
        DashboardController.php    # Phase 2
        UserController.php         # Super-Admin-only user management (Phase 2)
        ComingSoonController.php   # honest placeholders for not-yet-built nav sections (Phase 2)
        # planned: TemplateController, CertificateController, BatchController (later phases)
      VerificationController.php   # planned: public GET /verify/{codeword} (Phase 6)
    Middleware/
      EnsureUserIsActive.php       # 'active' alias — blocks a deactivated account mid-session (Phase 2)
    Requests/
      Admin/
        StoreUserRequest.php, UpdateUserRequest.php   # Phase 2
        # planned: StoreCertificateTemplateRequest, GenerateSingleCertificateRequest, etc.
  Policies/
    UserPolicy.php                 # Super-Admin-only user management, auto-discovered (Phase 2)
  Enums/
    UserRole.php, CertificateTemplateStatus.php, TemplateFieldType.php,
    CertificateBatchStatus.php, CertificateStatus.php    # Phase 2 — see docs/DATABASE_DESIGN.md
  Console/Commands/
    MakeAdminCommand.php           # `php artisan app:make-admin` — first production Super Admin (Phase 2)
  Models/
    User.php
    CertificateTemplate.php
    TemplateField.php
    CertificateBatch.php
    Certificate.php
    # planned: VerificationLog, AuditLog (Phase 6/9)
  Services/
    SiteContentService.php   # public-site computed content (eventPhase(), headerAlert(), etc. — Phase 1)
    # planned (Phase 4-8): Certificates/CertificateNumberGenerator, Certificates/CodewordGenerator,
    # Certificates/CertificateGenerationService, Certificates/CertificateRevocationService,
    # Templates/TemplateFieldSchemaService, Templates/PdfCoordinateService,
    # Pdf/CertificatePdfService, Qr/QrCodeService, Excel/ExcelTemplateExportService,
    # Excel/ExcelImportValidationService, Excel/BulkCertificateGenerationService
  Support/Site/
    Icons.php                # inline-SVG icon sprite, backs the @icon() Blade directive (Phase 1)

resources/
  views/
    components/
      layouts/
        app.blade.php         # public site chrome (Phase 1)
        admin.blade.php        # admin chrome (Phase 2)
      site/                   # public-site components: page-head, section-head, icon-card,
                               # person-card, cta-block, callout, stat-card, crumb, header, footer
    pages/                    # public pages: home, about, committee, membership, events, contact,
                               # events/becithcon-2026, events/hta-2026
    admin/
      auth/login.blade.php
      dashboard.blade.php
      coming-soon.blade.php   # one generic view for not-yet-built nav sections
      users/ (index, create, edit)
      # planned: templates/, certificates/, batches/ (later phases)
    verify/                   # planned (Phase 6): show, not-found, revoked

public/
  assets/                      # the EXISTING public-site css/js/img/pdf, copied across as-is
  css/admin.css                 # plain, no-build-step admin stylesheet (Phase 2)
  index.php                    # Laravel front controller (only publicly reachable PHP entry point)

database/
  migrations/
  seeders/
    DatabaseSeeder.php, AdminUserSeeder.php   # local/testing-only dev admin accounts (Phase 2)
  factories/

routes/
  web.php        # public site (Phase 1); verification (planned, Phase 6)
  admin.php      # admin panel — loaded from bootstrap/app.php's withRouting(then:) callback with
                 # the 'web' middleware group, 'admin' prefix and 'admin.' route-name prefix (Phase 2)

storage/
  app/
    private/      # planned: certificate-templates/{id}/, certificates/{id}.pdf, batches/{id}/*
                   # (not used yet — no uploads/generation exist in Phase 2)

tests/
  Feature/
    PublicSite/            # every legacy + current URL resolves and renders expected content
    Auth/
    Templates/
    Certificates/
    Verification/
    Excel/
  Unit/
    Services/
```

## 3. Public-site migration mapping

| Old (plain PHP) | New (Laravel) |
|---|---|
| `includes/config.php` `config()` | `App\Services\SiteContentService` (or `config/site.php` for the static parts + a small service for the computed `eventPhase()` logic) — same data, same shape, read by Blade components instead of raw PHP functions |
| `includes/components.php` helpers (`pagehead()`, `iconCard()`, `person()`, `svg()`, `ctaBlock()`, `statCard()`, `callout()`, `section_head()`) | Blade components: `<x-site.page-head>`, `<x-site.icon-card>`, `<x-site.person-card>`, `<x-site.icon>`, `<x-site.cta-block>`, `<x-site.stat-card>`, `<x-site.callout>`, `<x-site.section-head>` — same markup/classes, so `assets/css/style.css` needs no changes |
| `partials/head.php` | `<head>` section of `layouts/app.blade.php`, using `@section`/slots for per-page `$pageTitle/$pageDesc/$pageUrl/$pageImage/$pageJsonLd` |
| `partials/header.php` | `<x-site.header>` component (nav, alert banner, meta-nav, masthead) |
| `partials/footer.php` | `<x-site.footer>` component |
| `index.php`, `about.php`, `committee.php`, `contact.php`, `events.php`, `membership.php` | `resources/views/pages/*.blade.php` behind `PageController` actions |
| `becithcon-2026.php`, `hta-2026.php` | `resources/views/pages/events/*.blade.php` behind `EventController`, routed at `/event/becithcon-2026` and `/event/hta-2026` (same virtual path as today) |
| `.htaccess` clean-URL + legacy-redirect rules | Laravel routes are clean URLs natively; legacy `.html`/`.php`/old-slug redirects become explicit `Route::redirect(...)` entries — see `docs/MIGRATION_PLAN.md` for the exact list |
| `assets/*` | Copied verbatim into `public/assets/*`; **not** run through Vite — these are hand-authored, no-build-step files and stay that way to avoid any visual drift |
| `robots.txt`, `sitemap.xml`, `site.webmanifest` | Copied verbatim into `public/` |
| `voxel-qr.js` / `voxel-qr.css` (decorative 3D QR animation on the HTA page) | Copied as-is; unrelated to the certificate QR system — do not merge or confuse the two |

Admin/certificate UI is new and does **not** need to visually match the public site pixel-for-pixel
— it should read as "IEEE IUBAT" (same color tokens, same font) but can be a cleaner, denser admin
layout using Vite-built assets + Alpine.js.

## 4. Certificate system data flow

```
Template PDF (Canva export)
   -> upload -> stored in storage/app/private/certificate-templates/{id}/original.pdf
   -> admin defines template_fields (type, position in PDF points, style, show_on_verification)

Single certificate:
   TemplateFieldSchemaService reads template_fields
   -> generic Blade form rendered from field defs (no per-template view)
   -> GenerateSingleCertificateRequest validates against field defs
   -> CertificateGenerationService (DB transaction):
        CertificateNumberGenerator -> certificate_number
        CodewordGenerator -> codeword (unique, retry on collision)
        certificates row inserted (status=active, data=JSON of field_key=>value)
        CertificatePdfService renders PDF:
          FPDI imports original.pdf page as background
          writes each field at its stored PDF-point position
          QrCodeService builds https://.../verify/{codeword}, renders QR in-memory, drawn onto page
        PDF saved to storage/app/private/certificates/{id}.pdf
   -> admin downloads via a controlled, authorized route (never a public storage path)

Bulk certificates:
   ExcelTemplateExportService builds column headers directly from template_fields
   -> admin fills rows, uploads
   -> ExcelImportValidationService validates every row against the same field defs
      (required, type, dropdown options, date format, per-template duplicate rule) -> row-numbered
      errors, nothing generated yet
   -> admin reviews preview/error report
   -> BulkCertificateGenerationService processes valid rows in chunks, each row going through the
      exact same CertificateGenerationService used by the single-certificate path
   -> certificate_batches row tracks pending/processing/completed/partial/failed + counts
   -> ZIP of generated PDFs assembled for download, cleaned up by a scheduled command after a
      configured retention window (see docs/DEPLOYMENT_CPANEL.md §Cron)

Verification:
   GET /verify/{codeword}
   -> Certificate::where('codeword', $codeword)->first()
   -> not found -> "Certificate Not Verified" (no data leaked)
   -> status=revoked -> "No longer valid" (+ no personal data beyond that fact)
   -> status=active/reissued-superseded -> render only template_fields where
      show_on_verification=true, plus certificate_number and issued_at
   -> VerificationLog row recorded (result + timestamp only — no fingerprinting)
```

The same four services (`CertificateGenerationService`, `CertificatePdfService`, `QrCodeService`,
verification rendering) run identically regardless of which template is involved. That's what
makes "new template, zero PHP changes" true.

## 5. Frontend/build approach

- **Public site**: no build step. `public/assets/*` served as static files exactly as today.
- **Admin panel (Phase 2 — login, dashboard, user management)**: plain CSS
  (`public/css/admin.css`), no build step, no JS interactivity needed yet — standard HTML forms.
  Simpler than wiring up Vite for a handful of server-rendered pages; revisit if/when the template
  editor (Phase 4) actually needs Alpine.js-level interactivity, per the original plan below.
- **Admin panel (later, once real interactivity is needed — e.g. the template editor)**: Laravel's
  default Vite setup, kept minimal — Blade + Alpine.js for interactivity (dropdowns, wizard steps,
  live form preview). No React/Vue.
- **Template editor** (drag/place fields on a PDF preview): vanilla JS + **PDF.js** (Mozilla,
  BSD-licensed) to render the uploaded PDF's first page to a `<canvas>` in-browser for positioning.
  PDF.js ships pre-built; it's vendored/loaded as a static asset, not a Composer/npm runtime
  dependency — no Node process at runtime either way. Full mechanics in `docs/TEMPLATE_EDITOR.md`.
- Vite build output only matters at deploy time (`npm run build` produces static
  `public/build/*` assets that ship to cPanel); no Node process runs on the server itself.

## 6. Dependencies

| Package | Purpose | Why this one |
|---|---|---|
| `laravel/framework` ^12.0 | Application framework | Required target; PHP 8.2 minimum matches production exactly |
| `setasign/fpdi` | Import an existing PDF page as a certificate background | De facto standard for "write on top of an existing PDF" in PHP. **Open-source edition is limited to PDF ≤ 1.4** (no compressed xref streams) — must be validated against an actual Canva export before this is final; see the open question in the chat report / `docs/CERTIFICATE_SYSTEM.md` §PDF pipeline risk |
| `tecnickphp/tcpdf` | The PDF writer FPDI imports into; draws text/QR on top | Mature, actively maintained, strong Unicode/TTF font embedding (needed for Bangla names) |
| `endroid/qr-code` | QR generation | Actively maintained, PHP 8.1+, renders to PNG in-memory via GD (already available) — no need to persist QR files to disk |
| `maatwebsite/excel` (PhpSpreadsheet wrapper) | Excel template export + bulk import/validation | The standard Laravel Excel package; chunked reading keeps memory bounded on shared hosting, good validation/import hooks |
| `phpunit/phpunit` (dev) | Testing | **Decided in Phase 1/2** (superseding the original Pest plan below): kept whatever Laravel 12's `laravel new` scaffolded by default rather than swapping test frameworks before any real tests existed. Pest is a thin DSL over PHPUnit — revisit only if a concrete pain point with PHPUnit's syntax shows up; not worth the churn otherwise. |

No authentication/authorization package was added — Phase 2's admin login is a hand-rolled
`Admin\AuthController` (`Auth::attempt()`, session guard, `RateLimiter` for throttling) plus
Laravel's own Policy/Gate system (`App\Policies\UserPolicy`, auto-discovered) and a custom
`EnsureUserIsActive` middleware. No Breeze/Fortify/Jetstream/spatie-permission — two roles enforced
via a plain enum column don't need a permissions package, and there's no self-service registration
or password-reset flow for a starter kit to save work on. Revisit only if role/permission
complexity grows materially beyond "two fixed roles."

Packages considered and **not** chosen, with reasons, get added to this table as decisions are
made in later phases (e.g. Breeze/Fortify vs. hand-rolled auth — see `docs/SECURITY.md` §Auth).
No package is added to `composer.json` without a row here first.

## 6a. Local development environment — decided

Local development uses **Laravel Sail** (Docker-based), so the local PHP version matches
production exactly (PHP 8.2, MySQL) despite the host machine's system PHP being 8.5.8. Docker
Desktop is already installed on the development machine. Day-to-day commands go through
`./vendor/bin/sail` (`sail artisan`, `sail composer`, `sail test`, ...) instead of bare `php`/
`composer`/`artisan`. Sail is dev-only tooling — it is never part of the production cPanel
deployment (`docs/DEPLOYMENT_CPANEL.md`), which runs natively on the host's PHP 8.2 with no
Docker involved at all.

## 7. Hosting fit checklist

- No Redis: sessions/cache use the `database` driver; queue uses the `database` driver too.
- No queue worker daemon: background-style processing (bulk generation, ZIP cleanup) runs via
  cPanel Cron Jobs calling `php artisan queue:work --stop-when-empty` on a short interval, and
  `php artisan schedule:run` once a minute for scheduled cleanup — both ordinary cron entries, no
  long-running process.
- No Docker/Node at runtime: Vite build artifacts are pre-built and deployed as static files;
  `composer install --no-dev --optimize-autoloader` is the only build step that runs against the
  production PHP.
- MySQL only, via `pdo_mysql`/`mysqli` (both present in the hosting's module list).
