# Certificate System

The full lifecycle: template -> field definitions -> generation (single or bulk) -> QR ->
verification -> revocation/reissue. Read `docs/DATABASE_DESIGN.md` alongside this for the schema
each step reads/writes.

## Guiding rule

Everything here is driven by `template_fields` rows. No controller, service, or view may branch on
which template it's dealing with. If a new certificate type needs a code change beyond "create a
template and its fields," the design has regressed — see `docs/PROJECT_REQUIREMENTS.md`'s
acceptance test.

## PDF pipeline — open question to resolve early in Phase 4

Plan: **FPDI** imports the admin-uploaded Canva PDF's first page as a background; **TCPDF** (the
PDF FPDI writes into) then draws each `template_fields` row's value at its stored position, plus
the QR code and certificate number.

**Risk**: FPDI's free/open-source edition only supports importing PDFs up to version 1.4 (no
compressed cross-reference streams, introduced in PDF 1.5). Canva's PDF export is very likely
1.5+. Before Phase 4 is considered viable as scoped, get one real Canva-exported certificate PDF
and try importing it with plain `setasign/fpdi`. Three possible outcomes:

1. **It imports fine** — some PDF writers still emit classic (non-compressed) xref tables even at
   a nominal 1.5+ version. If so, proceed with the open-source stack as planned.
2. **It fails** — the practical options are: (a) buy Setasign's commercial **FPDI PDF-Parser**
   add-on (removes the version ceiling), or (b) have admins run the exported PDF through a tool
   that downgrades/flattens it to PDF 1.4 before upload (adds a manual step to the admin
   workflow). This is a cost/workflow decision, not a technical one — confirm with the user before
   committing either way.
3. There is no free server-side rasterize-to-image fallback available on this host: Imagick and
   Ghostscript are not in the confirmed PHP module list, so "render the PDF page to a PNG and use
   that as a raster background" isn't reliably available without adding a hosting dependency that
   may not exist on the target cPanel account.

Document the outcome and final decision in this file's changelog entry once resolved.

## Template lifecycle

1. Super Admin uploads a PDF (`StoreCertificateTemplateRequest`): validated by real content
   inspection (not just extension/MIME header — see `docs/SECURITY.md`), reasonable max size.
2. On upload, the page's width/height in points is read (FPDI/TCPDF can report this from the
   imported page) and stored on `certificate_templates` — this is what makes the coordinate
   conversion in `docs/TEMPLATE_EDITOR.md` possible without guessing.
3. Admin adds `template_fields` rows via the visual editor: type, label, key, required, style,
   position, `show_on_verification`.
4. Template starts `draft`; admin flips it to `active` when ready for generation. Only `active`
   templates are selectable in the single/bulk generation UI.
5. Editing an active template's fields is allowed but does not retroactively change already-issued
   certificates (their `data` JSON and rendered PDFs are frozen at issue time).

## Single certificate generation

1. Certificate Manager picks an active template.
2. `TemplateFieldSchemaService` builds the form fields from `template_fields` (no per-template
   Blade view — one generic form partial driven by `field_type`).
3. `GenerateSingleCertificateRequest` validates submitted values against each field's `is_required`
   / `field_type` / `options` (for dropdowns).
4. Optional preview step renders the PDF without persisting it (or persists a `draft`-scoped
   record — implementation detail decided in Phase 5; either way nothing is finalized/downloadable
   as a real certificate until confirmed).
5. On confirm, inside a DB transaction:
   - `CertificateNumberGenerator` produces the next human-readable number (template/year-scoped
     sequence, e.g. `IEEE-IUBAT-2026-000123` — format configurable, not hardcoded to one template).
   - `CodewordGenerator` produces a CSPRNG codeword; on a unique-constraint collision, retry.
   - `certificates` row inserted (`status=active`, `data` = validated field values).
   - `CertificatePdfService` renders the final PDF (background + fields + certificate number + QR
     pointing at `/verify/{codeword}`), saved to private storage.
6. Admin is redirected to a download (authorized, streamed — never a public storage URL).
7. Duplicate submission protection: the confirm action is a POST guarded the normal Laravel way
   (CSRF + a short-lived idempotency check, e.g. disable-on-submit client-side plus a server-side
   check against an in-flight duplicate within the same template/data before insert).

## Bulk (Excel) generation

1. Certificate Manager picks an active template, downloads the blank Excel template —
   `ExcelTemplateExportService` builds the header row directly from that template's
   `template_fields` (`sort_order`, `label`), so there is no separately maintained header list
   anywhere.
2. Admin fills rows offline, uploads (`UploadBatchRequest`: file type/size validated, real content
   sniffed, not just extension).
3. `ExcelImportValidationService` reads the file **without** generating anything yet, validating
   every row against the same field definitions:
   - required columns present
   - required values present per row
   - dropdown values are one of the configured options
   - date columns parse
   - duplicate rows within the file
   - a configurable per-template duplicate-vs-existing-certificate check (see §Duplicate control)
   - blank rows skipped/reported
   - row count within a sane maximum (chunked reading keeps memory bounded regardless)
   Errors come back row-numbered: `"Row 18: name missing"`, `"Row 25: invalid role"`.
4. Admin reviews a preview (valid rows + the error list) before anything is generated.
5. On confirm, `BulkCertificateGenerationService` creates a `certificate_batches` row and processes
   valid rows in bounded chunks (see `docs/ARCHITECTURE.md` §7 for the queue mechanism — Laravel's
   database queue, driven by cron-triggered `queue:work --stop-when-empty`, not a single giant
   synchronous request). Each row runs through the identical `CertificateGenerationService` the
   single-certificate flow uses.
6. A failing row is recorded against that row only; the batch finishes as `completed` (all rows
   good), `partial` (some failed), or `failed` (nothing succeeded) — never aborts the whole batch
   on one bad row.
7. On completion, generated PDFs are zipped for download; the ZIP is temporary (see cleanup
   policy in `docs/DEPLOYMENT_CPANEL.md`).

## QR code

- `QrCodeService` builds exactly one string per certificate: `{APP_URL}/verify/{codeword}`.
- Rendered via `endroid/qr-code` to an in-memory PNG (GD backend) and drawn directly into the
  TCPDF document — never written to disk as a standalone file. This avoids accumulating thousands
  of orphan QR PNGs, and matches the requirement that the QR never carries certificate data
  itself, only a URL that requires a live database lookup.
- Error-correction level: `M` (matches the archived prototype's choice — a reasonable default for
  printed certificates that may be photographed at an angle).

## Verification page (`GET /verify/{codeword}`)

- Looked up by exact `codeword` match (indexed, unique).
- **Not found** -> "Certificate Not Verified" page. No hints about why, no partial data.
- **Revoked** -> "This certificate is no longer valid." No further personal data shown beyond that
  status (per `docs/PROJECT_REQUIREMENTS.md`).
- **Active (or reissued-and-superseded, shown as historical)** -> shows: verified status,
  `certificate_number`, `issued_at`, template/event name, and only the `template_fields` rows
  where `show_on_verification = true` for that certificate's template, each labeled by
  `verification_label` (falling back to `label`). Never assumes a fixed field set (no certificate
  is guaranteed to have `role`/`session`/`institution`/`paper_id` — only render what that specific
  certificate's template actually defines and flags as public).
- All values echoed through Blade's default escaping (`{{ }}`) — never `{!! !!}` on anything
  sourced from `certificates.data`.
- Every lookup (hit or miss) writes one `verification_logs` row: `codeword_attempted`, `result`,
  and (on a hit) `certificate_id`. Rate-limited (see `docs/SECURITY.md`) to deter codeword
  enumeration without punishing a legitimate phone scanning the same QR a few times.

## Revocation

- Super Admin action, requires a reason (free text, stored in `revocation_reason`).
- Sets `status = revoked`, `revoked_at = now()`.
- Logged to `audit_logs`.
- Verification page immediately reflects the new status (no caching layer to invalidate — reads
  are a direct DB lookup).

## Reissue

- Super Admin action on an existing certificate (typically one that was revoked, or needs
  corrected data).
- Creates a **new** `certificates` row: new `certificate_number`, new `codeword`, fresh `data`
  (admin can adjust values), `reissued_from_id` pointing at the original.
- Original row's `status` becomes `reissued`; it is never deleted or overwritten. Its own
  `/verify/{codeword}` (the old codeword) should communicate that it has been superseded rather
  than pretending nothing happened — exact copy/behavior decided in Phase 8, but the original
  codeword must never silently start showing the new certificate's data.
- Logged to `audit_logs`, linking both records.

## Duplicate control

Not a blind "same name = duplicate" rule (the archived prototype's exact-match-on-name+role+
session approach doesn't generalize to arbitrary dynamic fields, and two genuinely different
people can share a name). Instead:

- At minimum, warn (don't hard-block) the Certificate Manager when a new single/bulk entry looks
  like an existing certificate for the same template, based on a configurable set of "identity"
  fields for that template (e.g. name + institution, or name + paper_id) rather than a fixed
  global rule.
- The check is a DB query against `certificates.data` (JSON) scoped to the same `template_id`, not
  a scan of any file.
- Exact mechanism (which fields count as "identity" per template) is a `template_fields`-level or
  `certificate_templates`-level configuration decided in Phase 3, not hardcoded per template.

## Excel — import/export only

MySQL is the only source of truth at all times. Excel files are:
- **Export**: the blank template built from `template_fields`, and later, ad hoc exports of a
  batch's or a template's generated certificates for reporting.
- **Import**: bulk-generation input, validated and then discarded as a source of truth (the file
  stays archived on `batches` storage for audit purposes, but is never re-read to answer "does
  this certificate exist" at runtime — the `certificates` table answers that).
- All exported values pass through the same formula-injection guard the archived prototype used
  (`safe_excel_text()` equivalent: prefix values starting with `= + - @`), applied server-side in
  `ExcelExportService`.

## Changelog

- _(Phase 0)_ Documented the pipeline and flagged the FPDI PDF-version risk as unresolved —
  needs a real Canva-exported sample PDF tested before Phase 4 begins.
