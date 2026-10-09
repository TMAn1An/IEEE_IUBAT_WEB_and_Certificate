# Architecture

## 1. Overview

One Laravel 12 application serving two audiences:

1. **Public website** — the existing IEEE IUBAT Student Branch site, migrated to Blade with
   preserved design/URLs/content.
2. **Certificate system** — admin-only, behind auth: template management, single + bulk
   certificate generation, and a public `/certificate/verify/{codeword}` endpoint (implemented
   Phase 6) that anyone, including an anonymous QR scan, can hit.

Both share one codebase, one database, one deployment, because that's what fits on cPanel shared
hosting without extra moving parts (no separate services, no Docker, no Node server at runtime).

## 2. Folder layout

**Status**: reflects what's actually built through Phase 6. Items still marked as planned (not yet
created) are for Phase 7 onward — see `docs/CHANGELOG.md` for what landed in which phase. The
Phase 4/5 PDF-designer files below are still present and still tested — Phase 6 paused that
workflow, it didn't remove it (see `docs/CERTIFICATE_SYSTEM.md` §Simple QR tool). The simple QR
tool itself was rebuilt once, mid-Phase-6, after inspecting the real reference tool
(`IEEEQRCODEGENERATOR-main/`) — the listing below reflects that rebuilt version; an earlier
`CertificateQrController`/`CertificateImportController`/`SimpleCertificateService` design that
incorrectly depended on `CertificateTemplate` was deleted, not kept as dead code.

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
        ComingSoonController.php   # honest placeholders for not-yet-built nav sections (Phase 2/3)
        TemplateController.php     # template CRUD + activate/archive (Phase 3) + background
                                     # upload/stream (Phase 4) — both admin roles
        TemplateFieldController.php # field CRUD + move-up/move-down (Phase 3)
        TemplateDesignerController.php # designer view + save-layout (Phase 4)
        CertificateController.php  # single-certificate issuance (PDF path, Phase 5, paused): choose
                                     # template, dynamic form, list/search, detail, PDF download
        QrTool/                    # Phase 6 (rebuilt twice — see docs/CERTIFICATE_SYSTEM.md
                                     # §Simple QR tool: old-tool-parity rebuild) — the simple QR
                                     # tool, fully independent of CertificateTemplate
          QrGenerateController.php       # THE old-tool-parity page: GET/POST /admin/qr-tool/generate,
                                          # one screen, no {category} param -- bound to config('qr-tool.
                                          # primary_category_slug'). Also QR PNG + Download Excel.
          QrOptionsController.php        # small JSON endpoints for the old tool's "Add role option"/
                                          # "Add type option"/"Add conference name" buttons, persisted
                                          # server-side instead of localStorage
          QrRecordsController.php        # full searchable list/detail for qr_certificates (secondary
                                          # to the main page's own inline "Recent Entries")
          QrGroupController.php          # Phase 6 (grouping) -- /admin/qr-tool/groups: list groups
                                          # + live record counts, per-group records/export
          QrImportController.php         # historical Excel import (old tool's real headings),
                                          # group-based (Phase 6, grouping) -- one implementation,
                                          # reached either per-group or via a group chooser first
          QrCategoryController.php       # multi-category CRUD (name/event_name/is_active, no PDF/
                                          # layout) -- exists for future categories; the main page
                                          # doesn't expose picking one, per explicit instruction
          QrCategoryFieldController.php  # field CRUD + move-up/move-down within a category
        DeletionRequestController.php  # Phase 7 -- request/index/approve/reject. requestForQr*()/
                                         # requestForCertificate() are model-bound per record type
                                         # (DeletableRecordType hard-coded server-side, never read
                                         # from request input) -- see docs/CERTIFICATE_SYSTEM.md
                                         # §Controlled deletion
        AuditLogController.php         # Phase 7 -- read-only Logbook. index() ONLY -- no store/
                                         # update/destroy method exists, on purpose
        # planned: BatchController (Phase 7)
      VerificationController.php   # Phase 6 — public GET /certificate/verify/{codeword}. Lives at
                                     # App\Http\Controllers, NOT App\Http\Controllers\Admin -- this
                                     # is the one certificate-related controller with no auth at all
    Middleware/
      EnsureUserIsActive.php       # 'active' alias — blocks a deactivated account mid-session (Phase 2)
    Requests/
      Admin/
        StoreUserRequest.php, UpdateUserRequest.php   # Phase 2
        Store/UpdateCertificateTemplateRequest.php    # Phase 3
        Store/UpdateTemplateFieldRequest.php          # Phase 3 — field-key format/uniqueness,
                                                        # dropdown-options, recipient-only-on-text
        UploadTemplateBackgroundRequest.php           # Phase 4 — mimes:pdf + magic-header check
        SaveTemplateLayoutRequest.php                 # Phase 4 — the designer's full save payload
        IssueCertificateRequest.php                   # Phase 5 — rules built dynamically from the
                                                        # selected template's fields; rejects
                                                        # unknown field keys (advanced PDF path only)
        GenerateQrRequest.php                         # Phase 6 -- field names match the old tool's
                                                        # own form exactly (role_select, conference_type,
                                                        # conference_select, include_conference, name,
                                                        # include_session, session), not the generic
                                                        # fields[{key}] shape used elsewhere
        UploadQrImportRequest.php                     # Phase 6 — mimes:xlsx + size check for the
                                                        # simple QR tool's Excel import upload step
                                                        # (authorizes on QrCertificate::create only --
                                                        # the route parameter is a QrGroup, not a
                                                        # QrCategory, as of the grouping feature)
        StoreQrCategoryRequest.php, UpdateQrCategoryRequest.php             # Phase 6
        StoreQrCategoryFieldRequest.php, UpdateQrCategoryFieldRequest.php   # Phase 6
        RequestDeletionRequest.php                    # Phase 7 -- reason required; authorize()
                                                        # checks DeletionRequestPolicy::create()
        RejectDeletionRequestRequest.php               # Phase 7 -- optional review_note; authorize()
                                                        # checks DeletionRequestPolicy::review()
        # planned: UploadBatchRequest, etc. (Phase 7)
  Policies/
    UserPolicy.php                 # Super-Admin-only user management, auto-discovered (Phase 2)
    CertificateTemplatePolicy.php  # both admin roles, auto-discovered (Phase 3); manageLayout()
                                     # ability added Phase 4 — blocks layout writes once archived
    CertificatePolicy.php          # both admin roles, auto-discovered (Phase 5) — viewAny/view/
                                     # create/download; no revoke/reissue ability yet (Phase 8).
                                     # viewDeleted() added Phase 7 -- super_admin only
    QrCategoryPolicy.php, QrCertificatePolicy.php   # Phase 6 — same two-role boundary, auto-discovered.
                                                      # QrCertificatePolicy gained viewDeleted() Phase 7
    DeletionRequestPolicy.php      # Phase 7 -- create() both roles; review()/viewAny() super_admin only
    AuditLogPolicy.php             # Phase 7 -- viewAny() super_admin only; deliberately no create/
                                     # update/delete ability defined at all
  Enums/
    UserRole.php, CertificateTemplateStatus.php, TemplateFieldType.php,
    CertificateBatchStatus.php, CertificateStatus.php    # Phase 2 — see docs/DATABASE_DESIGN.md
    # TemplateFieldType::assignable() added Phase 3 — the system-fields-vs-input-fields boundary,
    # see docs/CERTIFICATE_SYSTEM.md §System fields vs. input fields
    # CertificateStatus::GenerationFailed added Phase 5 — schema-readiness only, never written by
    # the current synchronous issuance flow (see docs/CERTIFICATE_SYSTEM.md §Failure handling)
    QrCategoryFieldType.php    # Phase 6 — text/long_text/number/date/dropdown only, no PDF-related cases
    QrCertificateStatus.php    # Phase 6 — Active/Revoked only, no Reissued/GenerationFailed
    DeletableRecordType.php    # Phase 7 -- the ONLY safe record_type mapping (qr_certificate |
                                 # certificate -> model class), never a raw client-supplied name
    DeletionRequestStatus.php  # Phase 7 -- pending/approved/rejected/completed
    AuditEventType.php         # Phase 7 -- deletion_requested/deletion_approved/deletion_rejected/
                                 # record_soft_deleted
  Console/Commands/
    MakeAdminCommand.php           # `php artisan app:make-admin` — first production Super Admin (Phase 2)
  Models/
    User.php
    CertificateTemplate.php        # background/layout columns + hasBackground() added Phase 4
    TemplateField.php              # is_recipient_name cast added Phase 3
    CertificateBatch.php
    Certificate.php                 # pdf_path/template_snapshot/layout_snapshot added Phase 5;
                                      # SoftDeletes added Phase 7 (deleted_at)
    QrCategory.php, QrCategoryField.php, QrCertificate.php   # Phase 6 — the simple QR tool's own
                                                               # models, no FK to any of the above.
                                                               # QrCertificate gained SoftDeletes
                                                               # (deleted_at) and qr_group_id Phase 7/6
    QrGroup.php                    # Phase 6 (grouping) -- auto-created Event Type + Event Name +
                                     # Role combination; no FK to QrCategory or CertificateTemplate
    QrConferenceType.php, QrConferenceOption.php   # Phase 6 -- persisted role/conference option
                                                     # lists, replacing the old tool's localStorage
    DeletionRequest.php            # Phase 7 -- certificate_deletion_requests. record() resolves the
                                     # actual QrCertificate/Certificate via the safe DeletableRecordType
                                     # mapping, withTrashed() (the record is usually already deleted
                                     # by the time anyone looks at a completed request again)
    AuditLog.php                   # Phase 7 -- audit_logs. Append-only (UPDATED_AT = null) -- see
                                     # docs/CERTIFICATE_SYSTEM.md §Logbook immutability
  Services/
    SiteContentService.php   # public-site computed content (eventPhase(), headerAlert(), etc. — Phase 1)
    Deletion/
      DeletionRequestService.php  # Phase 7 -- the ONLY path to a soft-delete. request()/approve()/
                                    # reject(), each in a DB::transaction() with lockForUpdate()
                                    # re-checks. approve() builds the compact deletion snapshot and
                                    # writes both a deletion_approved and a record_soft_deleted audit
                                    # entry -- see docs/CERTIFICATE_SYSTEM.md §Controlled deletion
    Templates/
      TemplateService.php        # slug generation, activation validation gate (Phase 3)
      TemplateFieldService.php   # field-key rules, recipient exclusivity, reordering (Phase 3)
      TemplateBackgroundService.php # PDF upload/replace storage (Phase 4)
      TemplateLayoutService.php     # save-layout transaction: page size, fields, system elements,
                                     # QR-squareness enforcement, IDOR re-check (Phase 4)
    Certificates/                # Phase 5 (PDF path, paused/advanced) -- see also QrTool/ below
                                   # (Phase 6, simple/primary), a fully separate sibling namespace
      CertificateIssuanceService.php    # Phase 5 — orchestrates PDF issuance in one DB transaction;
                                          # write-PDF-then-insert-then-cleanup-on-failure ordering
      CertificateNumberService.php      # atomic, race-safe sequential certificate numbers
      VerificationCodewordService.php   # CSPRNG codeword generation (CLAUDE.md's "verification
                                          # token", reusing the existing `codeword` column).
                                          # ACCEPTED_PATTERN is shared with the simple QR tool and
                                          # the public verification route — see
                                          # docs/CERTIFICATE_SYSTEM.md §Codeword compatibility
      CertificateSnapshotService.php    # builds template_snapshot/layout_snapshot at issuance;
                                          # extended Phase 6 to also capture show_on_verification/
                                          # verification_label per field (a real gap, fixed)
      TemplateFieldRules.php            # rule-builder for IssueCertificateRequest — advanced/PDF
                                          # path only (the simple QR tool has its own equivalent,
                                          # QrTool\QrCategoryFieldRules, deliberately not shared)
      QrCodeService.php                 # SHARED with the simple QR tool (see QrTool/ below) --
                                          # verification URL + draws the QR via TCPDF's native
                                          # write2DBarcode() (in-PDF) and pngBytes() via
                                          # TCPDF2DBarcode::getBarcodePngData() (standalone PNG) —
                                          # no new QR package either way. Knows nothing about either
                                          # domain model beyond a bare codeword string, which is why
                                          # it's safe to share across the isolation boundary
      IssuanceResult.php                # DTO: certificate + any field-overflow warnings (Phase 5)
      Import/
        ExcelFileReader.php             # SHARED with the simple QR tool -- reads a .xlsx's headers
                                          # + rows via PhpSpreadsheet, no schema assumptions at all
      Export/
        ExcelFormulaGuard.php           # SHARED with the simple QR tool -- prefixes a `= + - @`
                                          # leading cell value with an apostrophe before any Excel
                                          # export (CLAUDE.md's formula-injection rule)
      Verification/                # public verification -- serves BOTH sources
        CertificateVerificationService.php  # the one place the public route looks anything up:
                                              # QrCertificate first, then Certificate, each an exact
                                              # codeword match, then resolves status + public-field
                                              # visibility per source — see docs/CERTIFICATE_SYSTEM.md
                                              # §Snapshot/current-template fallback logic
        VerificationResult.php, VerificationOutcome.php, PublicField.php   # DTOs (dual-source shape)
    QrTool/                      # Phase 6 (rebuilt) — the simple QR tool, fully independent of
                                   # the Certificates/ namespace above except QrCodeService and
                                   # Import/ExcelFileReader (both genuinely generic — see above)
      QrToolCodewordService.php         # 16-char uppercase-alphanumeric CSPRNG codewords, matching
                                          # the real old tool's format exactly (see
                                          # docs/CERTIFICATE_SYSTEM.md §Codeword format compatibility)
      QrCategoryFieldRules.php          # rule-builder mirroring TemplateFieldRules, not shared
      QrCategoryService.php             # slug generation + primary() -- resolves the one category
                                          # the old-tool-parity page targets (config('qr-tool.
                                          # primary_category_slug')); no activation-validation gate
      QrCategoryFieldService.php        # field-key rules, recipient exclusivity, reordering, PLUS
                                          # addOption()/removeOption() for the old tool's "Add/Remove
                                          # role option" buttons — mirrors TemplateFieldService, not shared
      QrConferenceOptionService.php     # addType()/removeType()/addOption()/removeOption() for the
                                          # old tool's conference type/name add-remove buttons
      QrGroupService.php                # Phase 6 (grouping) -- the one find-or-create entry point
                                          # for QrGroup (resolve() from Event Type/Event Name/Role),
                                          # plus groupKey()/label()/exportFilename() helpers
      QrCertificateIssuanceService.php  # the no-PDF issuance path: codeword + DB row, PLUS
                                          # findDuplicate() reproducing the old tool's record_exists()
                                          # -- scoped to qr_group_id (Name+Session) as of grouping
      Import/
        QrImportMappingTarget.php       # codeword/created-at/ignore/conference-validate/role-
                                          # validate targets -- no per-row event-name override as of
                                          # grouping (the destination QrGroup is authoritative); no
                                          # separate "recipient name" target either, same reasoning
                                          # as the advanced importer's identical fix (see changelog)
        QrCategoryImportValidator.php   # single source of truth for mapping + per-row validation,
                                          # PLUS group-consistency + group-scoped duplicate checks
        QrCategoryImportService.php     # writes valid rows to qr_certificates (qr_group_id +
                                          # group-authoritative event_name/role), one transaction per
                                          # row (partial import is intentional)
        QrImportRowResult.php, QrImportSummary.php   # DTOs
        VerificationResult.php              # DTO the view actually receives -- NOT the Certificate
                                              # model, so codeword/id/pdf_path/template_snapshot/
                                              # data etc. are structurally unreachable from the
                                              # public Blade view, not just "not rendered"
        VerificationOutcome.php, PublicField.php   # enum + small DTO
      Pdf/                          # Phase 5 (paused, still present/tested)
        CertificatePdfService.php       # the FPDI/TCPDF renderer
        PdfPageBoxReader.php            # reads the real MediaBox/CropBox via FPDI's public,
                                          # standalone PdfReader API (not the protected
                                          # Fpdi::getPdfReader() the TCPDF import class uses)
        PdfPageBox.php                  # llx/lly/width/height value object
        PdfCoordinateConverter.php      # the one coordinate-conversion boundary — see
                                          # docs/CERTIFICATE_SYSTEM.md §Coordinate system
        CertificateFontResolver.php     # picks dejavusans vs. notosansbengali per field value
        CertificatePdfRenderResult.php  # DTO: PDF bytes + page box + overflow warnings
    # planned (Phase 7-8): Excel/ExcelTemplateExportService, Excel/ExcelImportValidationService,
    # Excel/BulkCertificateGenerationService, Certificates/CertificateRevocationService
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
      templates/ (index, create, edit — edit IS the detail/management page: metadata form,
                   background upload, field table, "Add field" link, Form Preview) — Phase 3,
                   background section added Phase 4
      templates/fields/ (create, edit, and a shared _form.blade.php partial both include) — Phase 3
      templates/designer.blade.php # the visual canvas editor (Phase 4) — see docs/CERTIFICATE_SYSTEM.md
      certificates/
        index, choose-template, issue, show   # Phase 5 (PDF path, paused/advanced) — see docs/CERTIFICATE_SYSTEM.md
        deleted.blade.php          # Phase 7 -- read-only "Deleted Certificates", super_admin only
      qr-tool/                     # Phase 6, primary workflow — fully independent of certificates/ above
        tool.blade.php             # THE old-tool-parity page -- Create Entry + Generated Result +
                                     # Recent Entries, all in one file, recreating
                                     # IEEEQRCODEGENERATOR-main/templates/index.html's actual layout
        records/ (index, show)     # the full searchable list (secondary to tool.blade.php's own
                                     # inline "Recent Entries")
        records/deleted.blade.php  # Phase 7 -- read-only "Deleted Records", super_admin only
        groups/ (index, show)       # Phase 6 (grouping) -- list auto-created groups + record
                                     # counts; per-group records list with Import/Export actions
        import/ (choose-group, upload, mapping, preview, result)   # group-based (Phase 6, grouping)
        categories/ (index, create, edit, fields/create, fields/edit, fields/_form.blade.php)
      deletion-requests/index.blade.php   # Phase 7 -- Super Admin review queue, Approve/Reject
      audit-logs/index.blade.php          # Phase 7 -- read-only Logbook, filterable
      components/admin/deletion-request-panel.blade.php   # Phase 7 -- shared between the QR record
                                                             # and advanced certificate detail pages
                                                             # so the request/pending/rejected UI
                                                             # never drifts between the two
      # planned: batches/ (Phase 7)
    verify/
      show.blade.php   # Phase 6 — ONE view, all three outcomes (verified/revoked/not-found)
                         # via a @switch on VerificationOutcome, not three separate files.
                         # Standalone HTML document, not x-layouts.app or the admin layout —
                         # reuses the public site's CSS variables/fonts for brand consistency
                         # without the full header/nav/footer chrome

public/
  assets/                      # the EXISTING public-site css/js/img/pdf, copied across as-is
  css/admin.css                 # plain, no-build-step admin stylesheet (Phase 2; designer layout
                                  # rules added Phase 4)
  js/admin/template-designer.js # vanilla JS: PDF.js render, drag/resize, coordinate conversion,
                                  # settings panel, sample preview data (Phase 4) — no build step,
                                  # matching the rest of the admin UI (see §5)
  js/admin/qr-tool.js           # vanilla JS adapted from the old tool's own inline script -- same
                                  # function names/behavior, role/conference add-remove now hit
                                  # small JSON endpoints instead of localStorage
  css/qr-tool.css               # vendored BYTE-FOR-BYTE from IEEEQRCODEGENERATOR-main/static/css/
                                  # style.css for visual parity -- deliberately not merged into
                                  # admin.css's theme
  index.php                    # Laravel front controller (only publicly reachable PHP entry point)

resources/
  fonts/                       # Phase 5, NOT public/ -- never served directly, only embedded into
                                 # generated PDFs server-side
    source/NotoSansBengali-Regular.ttf, NotoSansBengali-OFL.txt  # OFL-licensed source + license
    tcpdf/notosansbengali.{php,z,ctg.z}   # pre-converted TCPDF embedded font, committed so no font
                                            # conversion ever runs at request time or in production

database/
  migrations/
  seeders/
    DatabaseSeeder.php, AdminUserSeeder.php   # local/testing-only dev admin accounts (Phase 2)
  factories/
    CertificateFactory.php   # Phase 5

routes/
  web.php        # public site (Phase 1); verification (planned, Phase 6)
  admin.php      # admin panel — loaded from bootstrap/app.php's withRouting(then:) callback with
                 # the 'web' middleware group, 'admin' prefix and 'admin.' route-name prefix (Phase 2)

storage/
  app/
    private/      # certificate-templates/{id}/{uuid}.pdf (Phase 4), certificates/{year}/{uuid}.pdf
                   # (Phase 5) — both served only via authorized streaming controller routes, never
                   # a public storage URL. imports/{uuid}.xlsx (Phase 6) — a server-generated UUID
                   # only, deleted after a successful import; see docs/CERTIFICATE_SYSTEM.md
                   # §Excel import for why there's no persistent import-batch table instead.
                   # batches/{id}/* still planned (Phase 7).

tests/
  Feature/
    Admin/          # TemplateManagementTest, TemplateDesignerTest, CertificateIssuanceTest,
                     # UserManagementTest (actual layout — grouped by admin area, not by domain
                     # noun; see individual test files for the exact list each covers)
    PublicSiteTest.php       # every legacy + current URL resolves and renders expected content
    DatabaseSchemaTest.php   # explicit schema/column assertions, extended each phase
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

**Note**: this diagram describes the ADVANCED (Phase 5, PDF-path) system only, and predates the
real class names — kept for the high-level flow, which is still accurate for that system, but see
`docs/CERTIFICATE_SYSTEM.md` for what each phase actually built (e.g. `CertificateGenerationService`
below became `CertificateIssuanceService`; `CertificateNumberGenerator`/`CodewordGenerator` became
`CertificateNumberService`/`VerificationCodewordService`). The simple QR tool (Phase 6, the primary
admin workflow) is a separate, independent system not depicted here at all — see
docs/CERTIFICATE_SYSTEM.md §Simple QR tool for its own data flow. The Verification block below has
been corrected to match the real implementation, which serves both systems.

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

Verification (implemented Phase 6, serves BOTH systems -- see docs/CERTIFICATE_SYSTEM.md §Public verification):
   GET /certificate/verify/{codeword}  (public, routes/web.php, throttle:60,1)
   -> CertificateVerificationService::verify():
        QrCertificate::where('codeword', $codeword)->first()   -- checked FIRST
        else Certificate::where('codeword', $codeword)->first()
   -> not found on either table, OR an advanced Certificate's status is anything other than
      active/revoked (e.g. reissued) -> "Certificate Not Verified" (no data leaked)
   -> status=revoked (either table) -> "Certificate Revoked" (no personal/dynamic data)
   -> status=active (either table) -> "Certificate Verified": recipient_name, issued/created date,
      plus dynamic fields where show_on_verification=true. Advanced: certificate_number + template
      name, fields from template_snapshot if present else the live template (see
      §Snapshot/current-template fallback logic). Simple QR: event_name instead of a certificate
      number, fields always from the category's LIVE fields (no snapshot concept -- see §No
      snapshot). View receives a VerificationResult DTO either way, never either model directly.
   -> no VerificationLog / hit-miss audit table was built (still future work, out of scope for
      "public verification" specifically)
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
| `setasign/fpdi` ^2.6 (**added Phase 5**) | Import an existing PDF page as a certificate background | De facto standard for "write on top of an existing PDF" in PHP. Validated against the real demo Canva PDF (PDF 1.4, classic xref table) — imports cleanly, the version-ceiling risk did not materialize. See `docs/CERTIFICATE_SYSTEM.md` §PDF generation pipeline |
| `tecnickcom/tcpdf` **pinned `^6.8`** (**added Phase 5**) | The PDF writer FPDI imports into; draws text/QR on top | Strong Unicode/TTF font embedding (needed for Bangla names). **Must stay on 6.x** — `composer require` initially resolved the newest `7.0.10`, whose own package description now reads "Deprecated legacy PDF engine... use tc-lib-pdf instead"; that release throws a fatal error on `new TCPDF()` in this environment (font loading was restructured onto a separate, incompatible package). `setasign/fpdi`'s own `composer.json` pins its dev dependency to `tcpdf: ^6.8`, confirming 6.x is the tested-compatible line. `tc-lib-pdf` (the suggested replacement) has a different architecture FPDI cannot import into — not a viable alternative for this project. See `docs/CERTIFICATE_SYSTEM.md` §PDF generation pipeline for the full story. |
| ~~`endroid/qr-code`~~ — **not added** | ~~QR generation~~ | TCPDF (already a dependency for the reason above) bundles native 2D barcode/QR generation, both drawn into a PDF (`write2DBarcode()`) and as standalone PNG bytes (`TCPDF2DBarcode::getBarcodePngData()`, Phase 6) — a separate QR package would duplicate functionality already present. See `docs/CERTIFICATE_SYSTEM.md` §QR code |
| `phpoffice/phpspreadsheet` (**added Phase 6**) | Reads historical `.xlsx` files for the Excel import workflow | Chose the underlying library directly rather than `maatwebsite/excel` (which wraps it with queued-export/import abstractions this project doesn't need — Phase 6 only reads a few hundred rows synchronously within one request). Mature, actively maintained, PHP 8.2-compatible, no security advisories at install time. |
| ~~`maatwebsite/excel`~~ — **not added** | ~~Excel wrapper~~ | Would duplicate `phpoffice/phpspreadsheet` (which it wraps) for no benefit at this project's scale — see above. Revisit only if Phase 7's bulk PDF generation needs its queued-export helpers specifically. |
| `tman1an/formbuilder` (**Form + Page Builder package**, canonical source https://github.com/TMAn1An/formbuilder) | The Form Builder and Page Builder, extracted from this app into a reusable package. IEEE supplies only config, role/Logbook adapters and layout adapters — see `docs/FORM_BUILDER.md` | One canonical source instead of two diverging copies; consumed via Composer (VCS repository in production, a path repository only during local package development). Not on Packagist (yet). |
| `symfony/html-sanitizer` ^7.3 (**now a dependency of `tman1an/formbuilder`**, no longer required directly by this app; resolved 7.4.x; pulls in `masterminds/html5`) | Sanitizes admin-authored HTML in the Form/Page Builder (HTML blocks, custom HTML, page paragraphs) | A real DOM-parsing **allowlist** sanitizer is required: regex stripping is not safe against XSS. Maintained by Symfony; the 7.x line requires PHP ≥ 8.2 (the 8.x line needs 8.4, so the `config.platform.php = 8.2.31` pin keeps us on 7.x) and matches the Symfony 7 components Laravel 12 already ships. Chosen over `ezyang/htmlpurifier` (older API, much heavier configuration cache) since nothing else in the project uses either. |
| `phpunit/phpunit` (dev) | Testing | **Decided in Phase 1/2** (superseding the original Pest plan below): kept whatever Laravel 12's `laravel new` scaffolded by default rather than swapping test frameworks before any real tests existed. Pest is a thin DSL over PHPUnit — revisit only if a concrete pain point with PHPUnit's syntax shows up; not worth the churn otherwise. |

**Font asset, not a Composer package**: Noto Sans Bengali (SIL Open Font License) is embedded for
Bengali-script certificate text — fetched from Google's open-source font repository, converted
once into TCPDF's format via `TCPDF_FONTS::addTTFfont()`, and the output committed at
`resources/fonts/tcpdf/`. Not a package because it's a static asset consumed by TCPDF at runtime,
not a library with an API; see `docs/CERTIFICATE_SYSTEM.md` §Font strategy for the known
complex-script-shaping limitation this choice carries.

No authentication/authorization package was added — Phase 2's admin login is a hand-rolled
`Admin\AuthController` (`Auth::attempt()`, session guard, `RateLimiter` for throttling) plus
Laravel's own Policy/Gate system (`App\Policies\UserPolicy`, auto-discovered) and a custom
`EnsureUserIsActive` middleware. No Breeze/Fortify/Jetstream/spatie-permission — two roles enforced
via a plain enum column don't need a permissions package, and there's no self-service registration
or password-reset flow for a starter kit to save work on. Revisit only if role/permission
complexity grows materially beyond "two fixed roles."

**Phase 4 added zero Composer packages** — it never needed to parse or write a PDF server-side
(see `docs/CERTIFICATE_SYSTEM.md` §Certificate background & visual layout). Two browser-only
libraries were added instead, both loaded from cdnjs with a self-verified SRI hash, the same
pattern the original site already uses for `three.js`/`qrcode-generator` on the HTA page
(`reference/legacy-site/hta-2026.php` → carried into
`resources/views/pages/events/hta-2026.blade.php`) — not a new convention:

| Library | Purpose | Why this one |
|---|---|---|
| `pdf.js` 3.11.174 (cdnjs, `<script>` tag) | Renders the uploaded certificate PDF to a `<canvas>` in the browser, and provides the `viewport.convertToPdfPoint()`/`convertToViewportPoint()` coordinate math the designer relies on | Mozilla's own PDF renderer — the de facto standard, BSD-licensed. Pinned to the last version shipping a classic global (`pdf.min.js`/`pdf.worker.min.js`) build; 4.0+ moved to ES-module-only output, which would need `<script type="module">` for no benefit here. SRI hash independently verified by downloading the file and computing its own SHA-512, not copied blind from an API response |
| `qrcode-generator` 1.0.3 (cdnjs, `<script>` tag) | Renders a real, scannable sample QR in the designer's preview | Zero new dependency risk — already used elsewhere in this exact codebase for the HTA page's decorative QR widget; reusing the identical version/URL keeps there being only one `qrcode-generator` version pinned across the whole app |

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
