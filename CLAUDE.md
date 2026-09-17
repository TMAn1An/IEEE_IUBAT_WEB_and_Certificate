# CLAUDE.md — Permanent Project Rules

This file is binding for every future Claude Code session working on this repository. It does not
get relaxed because a task seems small. If a rule below blocks something you need to do, stop and
ask the user rather than working around it.

Read `docs/ARCHITECTURE.md`, `docs/DATABASE_DESIGN.md` and `docs/CERTIFICATE_SYSTEM.md` before
touching the certificate system. Read `docs/MIGRATION_PLAN.md` before touching any public-site
route or view.

## What this project is

Rebuilding the IEEE IUBAT Student Branch website (currently plain PHP, no framework, live at
`ieee.iubat.edu`) into one Laravel 12 application that also contains a full certificate
generation, template-editor and QR-verification system, deployable to ordinary cPanel shared
hosting (PHP 8.2.31, MySQL, no Docker/Redis/Node/Python at runtime).

The original static/plain-PHP site lives at the repository root (`index.php`, `about.php`, etc.)
and must **not be deleted** until its Laravel replacement has been verified against it page by
page. The old QR-generator prototype (Flask) is archived at
`reference/qr-generator/IEEEQRCODEGENERATOR-main/` — read-only reference material, never run in
production, never imported as a dependency.

## Non-negotiable security rules

- Never expose `.env`, DB credentials, admin credentials, API keys, or anything under
  `storage/app/private`.
- Never commit secrets. `.env` is gitignored; `.env.example` documents every key with safe
  placeholder values.
- Never modify the live production site while developing. All work happens locally / on staging
  first. Production changes only happen via the documented deployment procedure
  (`docs/DEPLOYMENT_CPANEL.md`), and only after the user explicitly approves the switch.
- CSRF protection stays on for every state-changing route (Laravel's default — never add routes
  to the CSRF exclude list without a documented reason).
- All admin routes sit behind auth + role middleware. Authorization is enforced server-side via
  Policies/Gates, never by hiding a button in Blade.
- Public registration does not exist. Admin accounts are created by an existing Super Admin (or
  via a one-time `php artisan app:make-admin` seeding command — see `docs/DEPLOYMENT_CPANEL.md`).
- Rate-limit `/verify/{codeword}` sensibly (deter enumeration, don't block real QR scanning from a
  phone on a shared network).
- Escape everything rendered from certificate `data` JSON on the public verification page. Never
  render user-controlled values as raw HTML.
- Excel export must guard against formula injection (prefix values starting with `= + - @` — see
  the pattern already used in the archived Flask tool, `safe_excel_text()`).

## Git workflow

- This repo is (or will shortly be) under Git. Make logical, scoped commits — one concern per
  commit, not one commit per file.
- Never rewrite working code without a documented reason recorded in `docs/CHANGELOG.md`.
- Run tests before considering a phase complete. Do not mark a phase done while tests are failing.

## Public website rules

- Preserve existing public URLs. If a URL's implementation changes internally, the old URL must
  still resolve (route to the same content, or 301-redirect) — see `docs/MIGRATION_PLAN.md` for
  the exact current + legacy URL map that must keep working.
- Preserve visual design, copy, images, navigation and responsive behavior. This is a framework
  migration (plain PHP → Blade), not a redesign. `assets/css/style.css` and `assets/js/main.js`
  carry across largely unchanged; do not introduce a CSS framework (Tailwind/Bootstrap) on the
  public pages.
- IEEE brand-lock constraints are hard requirements, not style preferences (see comments in the
  original `partials/header.php` / `footer.php`):
  - The IEEE enterprise meta-navigation links must never be reordered or renamed.
  - The IEEE Master Brand logo must stay ≥100×33px, white or black only, alt text exactly `"IEEE"`,
    linking to `https://www.ieee.org`.
  - The required IEEE administrative footer links (accessibility, nondiscrimination policy, ethics
    reporting, terms, privacy policy) must stay present and unmodified.
- Do not put business logic in Blade views. Page-specific data assembly belongs in a Controller or
  a dedicated `SiteContentService`-style class; Blade only renders.

## Certificate system rules — read `docs/CERTIFICATE_SYSTEM.md` for the full spec

- **Certificate fields are template-driven. This is the core acceptance criterion of the whole
  project.** Adding a new certificate template (new fields, new layout) must require zero PHP
  source changes. If you ever find yourself writing `if ($template->slug === 'keynote') { ... }`
  anywhere outside a seeder/demo script, stop — that is the exact anti-pattern this project exists
  to avoid. Fields come from `template_fields` and drive the single-certificate form, the Excel
  header row, Excel validation, PDF rendering, and the verification page display, all through one
  generic path.
- Keep certificate generation logic in `App\Services\CertificateGenerationService` (or similarly
  named dedicated services), never inline in a controller.
- Keep QR logic in a dedicated `QrCodeService`. QR codes encode only a verification URL
  (`/verify/{codeword}`) — never embed personal data directly in the QR payload.
- Keep Excel import/export logic in a dedicated `ExcelImportService` / `ExcelExportService`.
- Keep PDF rendering logic in a dedicated `CertificatePdfService`.
- `certificate_number` (human-readable, sequential-looking, safe to show) and `codeword` (random,
  the verification secret) are two different columns with two different purposes. Never use one
  for the other. Both are unique at the database level.
- Codewords are generated with PHP's CSPRNG (`random_int()` / `random_bytes()`), never
  `mt_rand()`, never derived from the row ID, never checked for uniqueness by scanning a file.
  Uniqueness is a DB unique constraint; on collision, regenerate and retry inside the transaction.
- Certificates are never destructively overwritten. Revoke sets `status = revoked` plus a reason
  and timestamp; it never deletes. Reissue creates a **new** `certificates` row linked back via
  `reissued_from_id`; it never mutates the original row's data.
- Excel is import/export only. MySQL is the only source of truth. Never read certificate state
  back out of an `.xlsx` file at runtime.
- Bulk generation must be chunked and resumable-in-spirit (a batch tracks `pending` /
  `processing` / `completed` / `partial` / `failed`; one bad row fails that row, not the batch).
  Never attempt to generate an unbounded number of PDFs inside a single synchronous HTTP request.

## Code quality

- Follow Laravel conventions: thin controllers, Form Requests for validation, Policies for
  authorization, Services for business logic, Eloquent models stay focused on relationships/casts/
  scopes rather than accumulating business logic.
- Use database transactions around any multi-step write (certificate creation, batch generation,
  reissue).
- No business logic in Blade. No inline SQL unless there's a specific, documented performance
  reason. No God classes. No speculative abstraction — build what the current phase needs.
- Prefer Blade + Alpine.js/vanilla JS for admin UI. Do not add React/Vue for a single screen.

## Package policy

Before adding any Composer or npm package: state the problem it solves, confirm it's actively
maintained and compatible with PHP 8.2 / Laravel 12, and check it isn't duplicating a package
already chosen. Record the decision and reasoning in `docs/ARCHITECTURE.md`'s dependency table —
don't just add it to `composer.json` silently.

## Testing

- Every significant feature ships with automated tests (Pest, per `docs/TESTING.md`).
- Tests assert database state and rendered content, not just HTTP 200.
- PDF/QR output additionally needs the manual QA pass documented in `docs/TESTING.md` — automated
  tests alone cannot verify a PDF looks right or a QR scans on a real phone.
- Run the test suite after each phase in `docs/MIGRATION_PLAN.md` before starting the next one.

## Documentation upkeep

- After any phase that changes behavior, update the relevant `docs/*.md` file and add an entry to
  `docs/CHANGELOG.md`. Documentation must stand on its own — a future maintainer (assume an IUBAT
  undergraduate, not someone who read this conversation) must be able to run and extend the
  project using only what's in the repo.
