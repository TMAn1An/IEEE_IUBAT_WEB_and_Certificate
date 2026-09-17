# Project Requirements

This is the durable requirements record, derived from the original project brief. Treat it as the
acceptance spec — if an implementation decision conflicts with something here, the requirement
wins unless the user explicitly changes it (and this file gets updated when they do).

## Goal

One Laravel 12 application, deployable to ordinary cPanel shared hosting (PHP 8.2.31, MySQL, no
Docker/Redis/Node/Python at runtime), containing:

1. The public IEEE IUBAT Student Branch website (migrated from plain PHP to Blade, design/URLs
   preserved).
2. An admin system with two roles (Super Admin, Certificate Manager) — no public registration.
3. A certificate template manager: admin uploads a Canva-exported PDF, defines dynamic fields
   visually, positions them, saves.
4. A dynamic certificate form system driven entirely by that template's fields.
5. Single certificate generation.
6. Bulk Excel certificate generation (download blank template -> fill -> upload -> validate ->
   preview -> generate -> ZIP download).
7. QR verification at `/verify/{codeword}`.
8. A certificate database (MySQL — never Excel) with revocation and reissue, preserving history.
9. Audit/history logging for significant admin actions.
10. A secure, privacy-conscious public verification page.

## Hard architectural principle (the acceptance test)

Certificate fields must be **template-driven data**, never hardcoded per certificate type. This
must work without any PHP source change:

1. Admin uploads a new Canva PDF.
2. Admin defines fields (e.g. `name`, `paper_title`, `paper_id`, `institution`) purely through the
   template editor UI.
3. Admin visually places `name`, `paper_title`, the certificate number, and the QR code.
4. Template saved.
5. A single-certificate form for exactly those fields appears automatically.
6. Generating one certificate creates a DB record, a unique certificate number, a unique codeword,
   a QR linking to `/verify/{codeword}`, and a PDF that visually matches the Canva template.
7. Scanning the QR shows the correct certificate info on the verification page.
8. The downloadable Excel template's headers automatically match the fields.
9. Twenty rows of fake data upload, validate, and generate twenty certificates, each with a unique
   codeword, each verifying correctly.
10. Admin creates a second, unrelated template (`name`, `role`, `session`) — no PHP/Laravel source
    file changes required to support it. Single and bulk generation both work for it immediately.

If satisfying a new template ever requires touching a controller or view file, the design is
wrong and needs to be corrected before continuing — this is the standard every phase from Phase 3
onward is measured against.

## Roles

- **Super Admin**: manage admins, manage certificate templates, manage certificates, manage
  settings, revoke/reissue, view logs.
- **Certificate Manager**: create certificates (single + bulk), use templates, view generated
  certificates, download certificates. Cannot manage users, templates are read/use-only unless
  explicitly granted otherwise.

Authorization is enforced server-side (Policies/Middleware), not by hiding UI elements.

## Certificate identity

- `certificate_number`: human-readable, e.g. `IEEE-IUBAT-2026-000123`. Safe to display publicly.
  Not the verification secret.
- `codeword`: cryptographically random verification identifier (CSPRNG, not sequential, not
  derived from the row ID), e.g. `7D9K2PX81AM3QW4Z`. This is what the QR encodes, as part of a URL
  — never the raw codeword alone, never certificate data.
- Both columns are unique at the database level. On generation collision, regenerate and retry.

## Certificate status

- `active`, `revoked`, `reissued` at minimum.
- Revoke: admin supplies a reason; status changes; verification page reflects invalid status;
  historical record is never deleted.
- Reissue: original record stays in history untouched; a new record is created with a new
  certificate number and codeword, linked back to the original via `reissued_from_id`.

## Bulk processing constraints

- Shared hosting has limited execution time/memory. Never attempt to generate an unbounded number
  of PDFs inside a single synchronous HTTP request.
- Batches are chunked; a failing row fails only that row, not the whole batch.
- Batch status: `pending`, `processing`, `completed`, `partial`, `failed`, with per-row failure
  detail retained for review.
- ZIP archives of bulk-generated certificates are temporary; a scheduled cleanup job removes them
  after a documented retention period (see `docs/DEPLOYMENT_CPANEL.md`).

## Security requirements (see `docs/SECURITY.md` for the full checklist)

CSRF, XSS, SQL injection, authorization/authentication, file-upload attacks, path traversal, MIME
spoofing, mass assignment, IDOR, session security, rate limiting, login brute-force protection,
verification-endpoint abuse, unsafe PDF uploads, unsafe spreadsheet values (formula injection),
untrusted HTML, secret exposure. `APP_DEBUG=false` in production; no stack traces or filesystem
paths shown to end users.

## Testing requirements (see `docs/TESTING.md`)

Automated coverage for auth, authorization, template CRUD + fields, single certificate creation,
unique codeword/certificate-number generation, Excel template generation + import validation,
bulk generation, verification (valid/invalid/revoked/reissued), file-upload validation,
unauthorized-access rejection, all public routes (existing + new), and the dynamic-field
"two different templates, one engine" scenario explicitly. Assertions check database state and
rendered content, not just HTTP 200. PDF/QR output additionally needs the manual QA checklist in
`docs/TESTING.md` since automated tests can't fully verify visual/scan correctness.

## Deployment requirements (see `docs/DEPLOYMENT_CPANEL.md`)

Staging before production. Live site untouched until the Laravel replacement is verified and
approved. Backups of the current `public_html` and database before any production switch.
`APP_ENV=production`, `APP_DEBUG=false`. Only `public/` is web-accessible; `.env`, `storage/`,
`vendor/` source stay outside the document root or otherwise blocked from direct HTTP access.

## Explicit non-goals for v1

- No image/signature field types yet (text/long_text/number/date/dropdown/certificate_number/
  qr_code only — see `docs/TEMPLATE_EDITOR.md`).
- No arbitrary Canva-text detection/replacement — the PDF background is fixed; only the
  admin-defined dynamic fields are written on top.
- No password-reset flow required (optional, only if practical without adding complexity).
- No React/Vue admin UI.
