# Certificate System

The full lifecycle: template -> field definitions -> generation (single or bulk) -> QR ->
verification -> revocation/reissue. Read `docs/DATABASE_DESIGN.md` alongside this for the schema
each step reads/writes.

## Guiding rule

Everything here is driven by `template_fields` rows. No controller, service, or view may branch on
which template it's dealing with. If a new certificate type needs a code change beyond "create a
template and its fields," the design has regressed — see `docs/PROJECT_REQUIREMENTS.md`'s
acceptance test.

## Dynamic field architecture — implemented Phase 3

`template_fields` is the single source of truth for what a certificate template needs. Every
consumer — the (future) single-certificate form, the (future) Excel header row and import
validation, the (future) PDF field placement, and the (future) verification page — reads this same
table and nothing else. Phase 3 built the admin CRUD for this table plus the two services that
keep it correct; the consumers themselves (form/Excel/PDF/verify) are later phases, but the schema
and rules they'll rely on are locked in now. `App\Services\Templates\TemplateService` and
`TemplateFieldService` hold every rule below — controllers stay thin (`$this->authorize()`,
Form Request, one service call).

**Field types (Phase 3 admin-assignable set)**: `text`, `long_text`, `number`, `date`, `dropdown` —
`App\Enums\TemplateFieldType::assignable()`. Configuring a field means: label, field key, type,
required, show-on-verification, dropdown options (JSON array of plain strings, e.g.
`["Keynote Speaker","Invited Speaker"]`), and sort order. No position/style (PDF coordinates,
fonts, colors) yet — that's Phase 4's template editor, layered on top of the same rows.

### System fields vs. input fields

`certificate_number` and `qr_code` are the other two `TemplateFieldType` cases, but they are
**not** admin-assignable input fields and never will be treated as one:

- They are never offered in the "Add field" type dropdown (`TemplateFieldType::assignable()`
  excludes them) and are rejected server-side if someone tries to force one through anyway
  (`Store/UpdateTemplateFieldRequest`).
- The strings `certificate_number` and `qr_code` are **reserved `field_key` values** — an ordinary
  text field can't use either as its key either, even though its own type would never be one of
  the system types (`TemplateFieldService::isValidFieldKey()`). This keeps the keys free for Phase
  4 to use as a lookup convention when placing the actual system-managed certificate-number text
  and QR image on the PDF.
- They are not participant-entered data: nobody fills in a "certificate number" on the
  single-certificate form, and they never become an Excel column. They are **layout elements** —
  Phase 4 gives them a PDF position (and, for the QR, nothing else — it has no label, no style
  beyond size) the same way it gives ordinary fields a position, but they stay conceptually
  separate from the fields loop everywhere else (the form, Excel, the `data` JSON, verification
  display).

### Recipient-name field

Exactly one field per template identifies which value becomes `certificates.recipient_name` once
generation exists (Phase 5) — see `docs/DATABASE_DESIGN.md` for why that column is denormalized.
Modeled as `template_fields.is_recipient_name` (boolean), not a template-level foreign key, so it
travels with field CRUD/reordering for free.

- Only a `text` field may carry it — rejected in the Form Request (`withValidator()`) if the
  submitted `field_type` isn't `text`, regardless of what `is_recipient_name` says.
- At most one per template — enforced by `TemplateFieldService::clearExistingRecipientField()`,
  called inside the same DB transaction as the create/update that sets a new one. Setting the flag
  on a field automatically clears it from whichever field held it before; this is automatic
  exclusivity, not a validation error asking the admin to unset the old one manually first.
- A draft template may temporarily have zero recipient fields while being built — only
  **activation** requires exactly one (see below), not every intermediate save.

### Field-key rules

`field_key` is the stable internal identifier a field's value lives under in a certificate's future
`data` JSON — never the display `label`, which can change freely without touching stored data or
breaking Excel imports already in flight.

- Format: `^[a-z][a-z0-9_]*$` — lowercase, starts with a letter, letters/digits/underscore only.
  Validated both in the Form Request (`regex:`) and centrally in
  `TemplateFieldService::isValidFieldKey()` (also checked again by `TemplateService`'s activation
  gate, for defense-in-depth against any future write path that bypasses the Form Request).
- Reserved: `certificate_number`, `qr_code` (see §System fields above) — rejected even though
  they'd otherwise pass the regex.
- Unique per template — a real DB unique constraint (`unique(certificate_template_id, field_key)`),
  backed by a matching Form Request `Rule::unique(...)` for a clean error message instead of a raw
  DB exception.
- **Editable in Phase 3.** No certificate has ever been generated against a template yet (that
  functionality doesn't exist until Phase 5), so there is nothing whose stored `data` JSON could
  go stale if a key changes. `TemplateFieldService::update()` and `delete()` both carry a comment
  at the exact spot a future guard belongs — e.g.
  `if ($field->template->certificates()->exists()) { /* block key change / block delete */ }` —
  so Phase 5 tightens this by adding a check, not by redesigning the method.

### Activation validation (draft → active)

`TemplateService::activationErrors()` (called by `activate()`, which throws when the list isn't
empty) checks, in order:

1. The template has at least one field.
2. Exactly one field has `is_recipient_name = true`.
3. No two fields share a `field_key` (defense-in-depth; the DB constraint already makes this
   unreachable in practice).
4. Every field's key passes `TemplateFieldService::isValidFieldKey()` (same reasoning).
5. Every `dropdown` field has at least one non-blank option.

Deliberately does **not** check for an uploaded PDF or any field having a PDF position — that's a
separate "generation ready" concept Phase 4 layers on top, not merged into this gate. A template
can be `active` with no PDF at all in Phase 3/4; Phase 5's single-certificate generation is the
first place that would need to additionally check for PDF/placement readiness before actually
producing a PDF. Archiving has no validation (an admin can always take a template out of
circulation); there's no "un-archive" action, but `activate()` works from any starting status, so
archiving is not one-way in practice — flip it back to `active` any time it passes validation
again.

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

**Decision (2026-09-17)**: proceed with the open-source `setasign/fpdi` + `tecnickphp/tcpdf` stack
as planned. Do not pre-purchase the commercial FPDI PDF-Parser add-on. The real test happens in
Phase 4 when a genuine Canva-exported certificate PDF is uploaded through the template editor for
the first time. If that import fails due to the PDF-1.4 ceiling, stop and get an explicit decision
from the user at that point (buy the commercial add-on vs. require admins to flatten/downgrade the
PDF before upload) rather than silently working around it.

## Template lifecycle

1. **(Phase 4, not yet built)** Super Admin uploads a PDF: validated by real content inspection
   (not just extension/MIME header — see `docs/SECURITY.md`), reasonable max size. On upload, the
   page's width/height in points is read and stored on `certificate_templates` — this is what
   makes the coordinate conversion in `docs/TEMPLATE_EDITOR.md` possible without guessing.
2. **(Phase 3 — built)** Either role (Super Admin or Certificate Manager — see
   `docs/PROJECT_REQUIREMENTS.md` §Roles) creates a template record (name, slug, description) via
   `Admin\TemplateController`, then adds `template_fields` rows one at a time via a plain form
   (`Admin\TemplateFieldController`): label, key, type, required, show-on-verification, recipient
   flag, dropdown options. No position/style yet (Phase 4). Field order is controlled by
   Move Up/Move Down (`TemplateFieldService::moveUp()`/`moveDown()`, a simple sort_order swap with
   the adjacent row) — full drag/drop placement is a separate, later concern (Phase 4's PDF
   canvas), not this list order.
3. A "Form Preview" on the template's management page renders what the future single-certificate
   form will look like — disabled inputs generated straight from the current `template_fields`
   rows, nothing persisted. This is the concrete proof that two differently-shaped templates
   produce two different forms through the same code path with no per-template branching — see
   the Phase 3 acceptance test in `docs/PROJECT_REQUIREMENTS.md`.
4. Template starts `draft`; an admin activates it once it passes §Activation validation below.
   Only `active` templates are meant to be selectable in the (future) single/bulk generation UI —
   that selection restriction itself is Phase 5 work, since generation doesn't exist yet.
5. Editing an active template's fields is currently allowed (no status-based lock on field
   mutations in Phase 3, since no certificate can reference a field yet either way). Once
   generation exists (Phase 5) and certificates can reference a template's fields, revisit whether
   editing an active template's fields needs a stronger guard than the field-key/delete comments
   already left in `TemplateFieldService` — but editing must never retroactively change
   already-issued certificates regardless (their `data` JSON and rendered PDFs stay frozen at
   issue time no matter what the template looks like afterward).

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
- _(Phase 3)_ Built template + dynamic-field CRUD, the recipient-name concept, field-key rules,
  the system-fields-vs-input-fields distinction, activation validation, and the form preview — see
  §Dynamic field architecture above. PDF/QR/Excel/generation sections above remain accurate
  descriptions of *planned* behavior for their respective later phases; nothing in this phase
  touched them.
