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
`["Keynote Speaker","Invited Speaker"]`), and sort order. Position/style (PDF coordinates, fonts,
colors) are a separate concern, added by the Phase 4 visual designer on top of these same rows —
see §Certificate background & visual layout below.

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
  Phase 4 gave them a PDF position (and, for the QR, nothing else — it has no label, no style
  beyond size), stored separately from `template_fields` (see §System-element layout storage
  below), and they stay conceptually separate from the fields loop everywhere else (the form,
  Excel, the `data` JSON, verification display).

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

## Certificate background & visual layout — implemented Phase 4

An admin uploads the Canva-exported PDF, visually positions every dynamic field plus the two
system elements on top of it, and the layout survives a reload byte-for-byte. This is the "prove
the dynamic architecture end-to-end, visually" milestone — no PDF is ever generated here (that's
Phase 5); everything below is about *capturing where things go*, in a format Phase 5 can consume
directly with no coordinate migration.

### Background handling

- **The uploaded PDF is the only master asset.** No server-side rasterization, no stored preview
  image, no thumbnail. `App\Services\Templates\TemplateBackgroundService::upload()` stores the file
  (UUID filename, `storage/app/private/certificate-templates/{template_id}/`, never the client's
  original filename — see `docs/SECURITY.md`) and records `original_filename`/`file_mime`/
  `file_size` for display, but never parses the PDF's internal structure.
- **Why not Imagick/Ghostscript-based rasterization**: Imagick happens to be present in the local
  Sail dev image, but it is **not** in the production cPanel host's confirmed PHP module list (see
  `docs/ARCHITECTURE.md`/Phase 0's inspection) — building a feature that only works locally and
  silently breaks in production is exactly the class of bug this project has been careful to avoid
  throughout (see the PHP-8.2-pinning story in `docs/ARCHITECTURE.md` §6a). Relying on client-side
  **PDF.js** instead (below) means the designer behaves identically in both environments, with zero
  server-side PDF parsing dependency.
- **Why not FPDI for validation either**: Phase 4 never asks FPDI to open the file (that's Phase
  5's job, rendering the final certificate). Content validation is `mimes:pdf` (real content
  sniffing via PHP's `fileinfo`/`finfo`, not the client-supplied extension or Content-Type) plus an
  explicit check that the file's first 5 bytes are the literal `%PDF-` header
  (`UploadTemplateBackgroundRequest`). Good enough to reject "renamed .txt file," which is the
  actual attack this guards against; genuine PDF-structure corruption would surface later, loudly,
  when PDF.js or (Phase 5) FPDI tries to open the file — not a silent security gap.
- **Replacing a background** (draft or active templates only — archived is read-only, see below):
  the old file is deleted only *after* the new one is stored and the database row committed, so a
  failed request never leaves a template with no valid file. Existing field/system-element
  positions are **not** auto-adjusted or cleared — the edit page shows a warning that dimensions may
  have changed and to re-check the designer before saving again, per the brief ("do not silently
  destroy all existing coordinates"). The actual comparison happens client-side: PDF.js reports the
  new file's real dimensions the moment the designer loads it, and that's compared against
  whatever was last saved.
- **Max size**: 10MB (comfortably covers the demo Canva PDF, which is ~1.3MB) — see §Canva demo PDF
  handling below for the actual file this was validated against.

### Coordinate system

All stored positions are in **PDF points** (1/72 inch), **not** browser pixels and **not**
percentages — the same units Phase 5's PDF writer will use directly, so there is exactly one
conversion boundary in the whole system (editor save time), matching the original plan in
`docs/TEMPLATE_EDITOR.md`.

The one refinement over that original plan, found while inspecting the actual demo PDF (see
§Canva demo PDF handling): **conversion uses PDF.js's own `viewport.convertToPdfPoint()` /
`convertToViewportPoint()`, not a hand-rolled y-flip formula.** The demo certificate's PDF page has
a `MediaBox` of `[0.0 8.579974 842.25 604.07996]` — its origin is *not* `[0,0]`, a small but real
Canva export quirk. A naive formula assuming a zero origin (`y_pt = page_height - canvas_y`) would
misplace every element on a PDF like this one. PDF.js's viewport transform already accounts for
the page's actual `MediaBox`, so routing every conversion through it is correct regardless of
whether a given PDF happens to have a zero or non-zero origin — the designer never needs to know
which case it's looking at.

```
public/js/admin/template-designer.js:

screenBoxToPdf(box)   // {left, top, width, height} canvas px (top-left origin)
                       // -> {x, y, width, height} PDF pt (bottom-left origin)
pdfBoxToScreen(box)    // the inverse, for rendering already-saved positions on load
```

Both take the two opposite corners of a box, convert each through the viewport, and take the
min/max — this normalizes the result regardless of the y-flip (a PDF-space bottom-left corner maps
to a *larger* canvas-pixel Y than the top-right corner does, since canvas Y grows downward while
PDF Y grows upward).

`page_width`/`page_height` on `certificate_templates` are updated as a side effect of the first
"Save Layout," not at upload time — PDF.js reads the true page size directly from the file on every
designer load regardless of what's cached in those columns, so there's no risk of the designer
displaying stale dimensions; the columns are a record for other parts of the app (e.g. the template
list) to read without needing to open the PDF again.

### Position JSON (`template_fields.position`)

Unchanged shape from what Phase 2 already had ready:

```json
{ "x": 420.5, "y": 310.2, "width": 700, "height": 70 }
```

`(x, y)` is the box's **bottom-left** corner in PDF points. Written by
`App\Services\Templates\TemplateLayoutService::saveLayout()`, one DB transaction per save covering
every field plus both system elements together — either the whole layout save succeeds or none of
it does (see CLAUDE.md's "use transactions for multi-step writes" rule).

### Style JSON (`template_fields.style`)

```json
{ "font_size": 34, "font_weight": "bold", "alignment": "center", "line_height": 1.2, "color": "#000000", "wrap": true }
```

Exactly the shape suggested in the brief. `font_weight` is `normal`|`bold` only (no numeric
weights — Phase 4 doesn't need finer granularity, and TCPDF's bold flag in Phase 5 is binary
anyway). `alignment` is `left`|`center`|`right`. `color` must match `^#[0-9a-fA-F]{6}$` — this is
also the injection guard: a value that isn't a plain 6-digit hex code is rejected outright, so
nothing from a style field can ever reach rendered HTML/CSS as anything other than a validated
color (`SaveTemplateLayoutRequest`).

### System-element layout storage

`certificate_number` and `qr_code` are **not** rows in `template_fields` — the brief was explicit
about this, and it also fits the existing architecture better: there are exactly two of these per
template, always, with no variable cardinality the way dynamic fields have. Storing them as two
nullable JSON columns directly on `certificate_templates`
(`add_background_and_layout_columns_to_certificate_templates_table`) was chosen over a new
`template_layout_elements` table because a table earns its keep when rows have independent
lifecycle/cardinality — these don't; there will never be a third one without a schema change
either way, so a table would only add a join for no real flexibility gained. Two focused columns
read exactly like the rest of the schema's existing JSON-column convention (`options`, `position`,
`style` on `template_fields`).

```json
// certificate_number_layout
{ "x": 40, "y": 30, "width": 220, "height": 24, "style": { "font_size": 12, "font_weight": "normal", "alignment": "left", "color": "#666666" } }

// qr_code_layout
{ "x": 740, "y": 20, "width": 80, "height": 80 }
```

QR is square by default and *stays* square regardless of what the browser submits —
`TemplateLayoutService::squareQrLayout()` always overwrites `height` with `width` server-side, so
even a client-side bug or a hand-crafted request can't desync it. `certificate_number` reuses a
subset of the field style shape (no `line_height`/`wrap` — a certificate number is a single short
line, those settings don't apply).

### Sample preview data

The designer shows realistic sample values instead of empty boxes, purely client-side
(`sampleValueFor()` in `template-designer.js`) — nothing here is stored or touches the database.
Driven by `field_type` first, refined by simple `field_key` substring heuristics (`name` → "John
Doe", `institution`/`organi...` → "Example University", `title` → "A Sample Research Paper Title",
`id` → "1570000012", etc., falling back to a type-based generic otherwise). This is a uniform
heuristic applied to *any* template's field naming conventions — not a per-template branch, so it
doesn't violate the "never write `if ($template->slug === ...)`" rule in CLAUDE.md. Certificate
number's sample is the fixed string `IEEE-IUBAT-2026-0001`; the QR sample renders a real scannable
QR (via `qrcode-generator`, the same CDN-loaded library the original site's HTA page already
used — see §Canva demo PDF handling for why no new QR dependency was introduced) encoding a
throwaway `/verify/SAMPLE-CODE` URL, purely so the designer shows what a real QR will look like at
the chosen size.

### Template status rules

- **Draft**: fully editable (background, fields, layout).
- **Active**: also fully editable in Phase 4, per the brief's explicit preference ("active
  templates can still be edited during development unless existing business rules strongly suggest
  otherwise") — no code currently depends on an active template's layout being frozen, and nothing
  yet reads `position`/`style` to produce a real certificate (Phase 5).
- **Archived**: read-only. `App\Policies\CertificateTemplatePolicy::manageLayout()` returns `false`
  once a template is archived, checked by both the designer's save action and the background-upload
  action (`SaveTemplateLayoutRequest`/`UploadTemplateBackgroundRequest` both authorize against it).
  *Viewing* the designer stays allowed for an archived template (reads `update`, the same ability
  every other template-management page uses) — an admin can still see how an archived template was
  laid out, just not change it.

### Server-side validation

The browser is never trusted to have kept numbers sane, regardless of what the editor's own UI
prevents (`SaveTemplateLayoutRequest`):

- `page_width`/`page_height`: numeric, `1`–`5000`pt.
- Every position's `x`/`y`: must land within the page bounds plus a small (20pt) tolerance for
  intentionally edge-anchored elements — "wildly outside the canvas" (e.g. `x: 99999`) is rejected,
  verified live against the actual demo PDF's dimensions.
- Every position's `width`/`height`: minimum 2pt (10pt for QR), and can't exceed the page's own
  dimensions (plus the same small tolerance).
- Style `font_size`: 4–300pt. `font_weight`/`alignment`: restricted to the fixed value sets above,
  not free text. `color`: hex-only regex, the injection guard described above.
- **IDOR**: every `fields.*.id` in a save payload is re-checked against the `{template}` in the
  URL, both in the Form Request and again in `TemplateLayoutService::saveLayout()` — a field
  belonging to a different template is rejected with a clear error, verified live by attempting
  exactly that.

### Canva demo PDF handling

Inspected the actual attached demo certificate (`Demo Certificate.pdf`) rather than assuming:
**PDF 1.4** (`%PDF-1.4` header), a classic (non-compressed) cross-reference table — no `/Type
/XRef` or `ObjStm` objects — single page, `MediaBox [0.0 8.579974 842.25 604.07996]` (≈ A4
landscape, 297×210mm, with a small non-zero origin offset), `Producer`/`Creator` both `Canva`,
1.3MB. Two concrete decisions this shaped:

1. **PDF.js's own coordinate conversion, not a hand-rolled formula** — directly because of the
   non-zero `MediaBox` origin found on this real file (see §Coordinate system above).
2. **The free/open-source FPDI risk flagged in Phase 0 looks smaller than originally feared** — a
   PDF 1.4 file with a classic xref table is exactly the case free FPDI *can* import. This is one
   data point, not a closed question (a different Canva export could still land on 1.5+ with
   compressed xref streams), so Phase 0's decision to test with a real file at the point FPDI is
   actually introduced (Phase 5) stands — but it's a meaningfully more optimistic starting point
   than "unknown."

No PNG/preview image was generated from this file at any point — the designer renders the PDF
directly via PDF.js on every load, confirmed working against this exact file during manual QA (see
the Phase 4 completion report).

## PDF generation pipeline — implemented Phase 5

**Resolved**: the real demo Canva certificate (`Demo Certificate.pdf`, PDF 1.4, classic
non-compressed xref table) imports cleanly with free `setasign/fpdi` — the version-ceiling risk
flagged in Phase 0/4 did not materialize. No commercial FPDI PDF-Parser add-on was purchased.

**Package pin — important**: `tecnickcom/tcpdf` must stay on the **`^6.8`** line, not `^7.0`.
`composer require` initially pulled `tcpdf:7.0.10`, whose package description itself now reads
"Deprecated legacy PDF engine for PHP. Use instead tecnickcom/tc-lib-pdf." That 7.x release has
been restructured to load fonts through a new `tecnickcom/tc-lib-pdf-font` package, and it threw a
fatal error (`unable to read file: helvetica.json`) on the very first `new TCPDF()` call in this
environment — it is not a drop-in-compatible release for this stack. `setasign/fpdi`'s own
`composer.json` pins its dev/test dependency to `tecnickcom/tcpdf: ^6.8`, confirming 6.x classic
(self-contained font system) is the version FPDI is actually tested against. `tc-lib-pdf` (the
suggested replacement) has a different architecture FPDI cannot import into at all — it isn't a
viable alternative for this project regardless. Classic TCPDF 6.x is still receiving updates as of
the version installed (6.11.4); revisit only if that changes.

**Zero new packages for QR**: TCPDF bundles native 2D barcode generation
(`TCPDF::write2DBarcode()`, real vector output, not a rasterized image) — see §QR code below.

### Coordinate system

Verified against the real demo certificate's raw bytes: `/MediaBox [0.0 8.579974 842.25
604.07996]` — width 842.25pt (matches Phase 4's designer number), but **height is 595.5pt**
(`604.07996 - 8.579974`), not the `604.08` Phase 4's manual QA notes recorded. That number was
never actually confirmed against a real browser session in Phase 4 (no headless browser was
available then either — see the Phase 4 report); it was the raw MediaBox `ury` value, not
`ury - lly`. This is a documentation correction only, not a data-migration concern: no template's
`page_width`/`page_height` had ever actually been written by a real PDF.js session, so nothing
stored was wrong, only what one changelog note claimed. It's also why point 12 below (never trust
those two columns for rendering) matters in practice, not just in theory.

Two coordinate spaces are in play once FPDI is involved, and they are **not** the same:

- **Phase 4's stored `position`/`certificate_number_layout`/`qr_code_layout`** are in RAW PDF
  points exactly as PDF.js's `convertToPdfPoint()` produces them — relative to the page's true,
  possibly non-zero-origin MediaBox. For the real demo certificate, valid `y` values range from
  `8.58` to `604.08`, not `0` to `595.5`.
- **FPDI's `importPage()`** normalizes the imported XObject so the page box's own lower-left
  corner becomes local `(0, 0)` — it subtracts `(llx, lly)` internally when building the placement
  matrix (`e = -bbox->getLlx(); f = -bbox->getLly();` in `FpdiTrait::importPage()`). TCPDF's own
  public drawing API (`SetXY`/`Cell`/`MultiCell`/`Rect`) then uses a **top-left origin, y growing
  downward**, relative to that same normalized page.

So converting a raw Phase-4 box to a TCPDF box takes two steps — both done in exactly one place,
`App\Services\Certificates\Pdf\PdfCoordinateConverter::toTcpdfBox()`, and nowhere else:

1. Subtract `(llx, lly)` to land in FPDI's normalized space.
2. Flip `y` relative to the normalized page height: `tcpdfY = normalizedHeight - normalizedY - boxHeight`.

`llx`/`lly`/normalized width+height are read via `App\Services\Certificates\Pdf\PdfPageBoxReader`,
which uses FPDI's **standalone, public** `PdfReader`/`PdfParser`/`StreamReader` classes (not the
protected `Fpdi::getPdfReader()` used internally by the TCPDF import class) — this lets dimensions
be read independently, without first building a full TCPDF document.

**`certificate_templates.page_width`/`page_height` are never trusted for rendering.** They're a
Phase 4 designer-UI convenience (populated only as a side effect of clicking "Save Layout" in a
browser), not a reliable source for generation. `CertificatePdfService` always re-derives real
dimensions from the actual stored PDF file via `PdfPageBoxReader` at generation time — proven
necessary by the `595.5` vs `604.08` discrepancy above, which is exactly the kind of drift a
"trust the cached column" approach would have baked into every generated certificate.

This was proven correct against the real demo certificate in a manual spike (not just unit-tested
against a synthetic fixture): text placed at raw `y=560` landed near the top of the page; text at
raw `y=20` landed flush with the true bottom edge (only ~11pt above the actual MediaBox origin);
a marker square at the computed bottom-right position landed correctly. See the Phase 5 completion
report for the rendered proof image.

### Font strategy

TCPDF has no HarfBuzz-style text-shaping engine, so it cannot correctly mix scripts within one
`Cell`/`MultiCell` call, and per-character script-run segmentation was judged out of scope for
Phase 5 (real names are overwhelmingly single-script). `App\Services\Certificates\Pdf\
CertificateFontResolver` picks ONE embedded font for a field's entire value:

- **`dejavusans`** (bundled with TCPDF) for anything without Bengali codepoints — full Unicode
  coverage for Latin script, including diacritics (`José García`).
- **`notosansbengali`** for any value containing Bengali script (U+0980–U+09FF) — **Noto Sans
  Bengali**, SIL Open Font License, fetched from Google's open-source font repository
  (`resources/fonts/source/NotoSansBengali-Regular.ttf` + `NotoSansBengali-OFL.txt`) and
  pre-converted once into TCPDF's embedded format via `TCPDF_FONTS::addTTFfont(...)`, committed at
  `resources/fonts/tcpdf/notosansbengali.*`. No conversion happens at runtime or in production —
  the committed files are loaded directly via `AddFont()`.

**Known, documented limitation**: this renders real Bengali glyphs (verified against
`মোঃ মাইনুল ইসলাম` in the spike — no more empty "tofu" boxes, which is what DejaVu Sans alone
produces), but TCPDF maps Unicode codepoints to glyphs without a full shaping engine, so correct
ligature substitution/reordering for complex conjunct clusters (যুক্তাক্ষর) is not guaranteed.
Common names rendered correctly in testing. This is a deliberate scope decision, not an oversight —
building a shaping-aware renderer now would be solving a problem that hasn't actually occurred;
revisit only if real Bengali certificates show visibly wrong glyphs.

Bold is only genuinely embedded for `dejavusans` (`dejavusansb`, TCPDF-bundled); Bengali bold text
silently falls back to the regular Bengali weight (no bold Bengali TTF is embedded) — acceptable
for Phase 5, noted for anyone revisiting font handling later.

## Template lifecycle

1. **(Phase 3 — built)** Either role (Super Admin or Certificate Manager — see
   `docs/PROJECT_REQUIREMENTS.md` §Roles) creates a template record (name, slug, description) via
   `Admin\TemplateController`, then adds `template_fields` rows one at a time via a plain form
   (`Admin\TemplateFieldController`): label, key, type, required, show-on-verification, recipient
   flag, dropdown options. Field order is controlled by Move Up/Move Down
   (`TemplateFieldService::moveUp()`/`moveDown()`, a simple sort_order swap with the adjacent row)
   — this is the field *list* order (drives the single-certificate form / Excel columns), separate
   from the visual *position* set in the designer below.
2. **(Phase 4 — built)** An admin uploads a PDF (`Admin\TemplateController::uploadBackground()`):
   validated by real content inspection, not just extension/MIME header (see `docs/SECURITY.md`).
   No page-dimension reading happens at upload time — the designer reads the true page size
   directly from the file via PDF.js on every load (see §Certificate background & visual layout
   above for why, and why no FPDI/Imagick dependency was needed for this).
3. A "Form Preview" on the template's management page renders what the future single-certificate
   form will look like — disabled inputs generated straight from the current `template_fields`
   rows, nothing persisted. This is the concrete proof that two differently-shaped templates
   produce two different forms through the same code path with no per-template branching — see
   the Phase 3 acceptance test in `docs/PROJECT_REQUIREMENTS.md`.
4. **(Phase 5 — built)** Template starts `draft`; an admin activates it once it passes
   §Activation validation below. Only `active` templates are selectable in
   `Admin\CertificateController::chooseTemplate()`/`create()` — enforced both there and again
   inside `CertificateIssuanceService::issue()` (defense in depth, not just a UI filter).
5. **(Phase 5 — decided)** Editing an active template's fields/layout/background stays allowed
   even after certificates exist for it — deliberately NOT blocked. Every issued certificate
   stores a full `template_snapshot` + `layout_snapshot` at issuance time (see §Snapshot strategy
   below), so a later label change, a dragged field, or even a replaced background can never
   retroactively alter an already-issued certificate's stored data or its already-generated PDF —
   both are frozen copies, not references back to the live template. The practical rule adopted:
   **the template is free to evolve for future issuance; only already-issued certificates are
   guaranteed immutable, and that immutability comes from the snapshot, not from freezing the
   template.** This was chosen over blanket-blocking all edits once certificates exist, which
   would have made routine fixes (a typo in a field label, nudging a misaligned field) impossible
   on any template that had ever been used — a worse outcome for no real safety gain, since the
   snapshot already makes historical edits safe.

## Single certificate generation — implemented Phase 5

`Admin\CertificateController` + `App\Services\Certificates\CertificateIssuanceService`:

1. Certificate Manager or Super Admin picks an active template
   (`GET /admin/certificates/issue` → `GET /admin/certificates/issue/{template}`).
2. The issuance form (`resources/views/admin/certificates/issue.blade.php`) is generated directly
   from that template's `template_fields` — the same field-type switch pattern as Phase 3's Form
   Preview, but with real, submittable inputs.
3. `App\Http\Requests\Admin\IssueCertificateRequest` builds validation rules **dynamically** from
   the template's fields — never trusts the browser-rendered form:
   - `required`/`nullable` from `is_required`.
   - type rules per `field_type` (`string`/`max:`, `numeric`, `date`, `Rule::in($field->options)`
     for dropdowns).
   - a `withValidator()->after()` pass rejects any submitted `fields.*` key that isn't one of the
     template's own assignable field keys (rejects both typos and a deliberately manipulated
     `fields[certificate_number]` — certificate numbers are never accepted from the client, only
     generated server-side; see the "certificate number cannot be manipulated by client" test).
4. `CertificateIssuanceService::issue()` runs everything else inside **one DB transaction**:
   - Confirms the template is `active` and has a background (defense in depth beyond the
     controller/policy checks).
   - Reads the one field flagged `is_recipient_name` and copies its submitted value into
     `certificates.recipient_name` directly — the admin never types a name separately.
   - `CertificateNumberService::next()` mints the certificate number (see §Certificate number
     generation below).
   - `VerificationCodewordService` mints a CSPRNG codeword, retried up to 5 times against the DB
     unique constraint on collision (CLAUDE.md: CSPRNG only, never `mt_rand()`, never derived from
     the row id).
   - `CertificatePdfService::render()` produces the final PDF **entirely in memory** (background +
     every field + certificate number + QR).
   - The PDF is written to the private disk, then the `certificates` row is inserted referencing
     that path, `template_snapshot`, and `layout_snapshot`.
5. Admin is redirected to `GET /admin/certificates/{certificate}` — a detail page with an inline
   PDF preview (`<iframe>` against the authorized download route) and a real download link. The
   PDF is only ever served through `Admin\CertificateController::download()`
   (`Storage::disk('local')->response(...)`, policy-checked) — never a public storage URL.
6. Duplicate submission protection: POST/Redirect/GET (the success response is a redirect, so a
   browser refresh re-GETs the detail page rather than resubmitting) plus the issue form's submit
   button disables itself on submit (`resources/views/admin/certificates/issue.blade.php`). No
   server-side idempotency token was added on top of this — the brief explicitly said not to
   over-engineer this, and PRG + a disabled button covers the realistic accidental-double-click
   case; a genuinely malicious double-POST would simply mint two distinct, both-valid certificates
   (not a data-corruption risk, since numbers/codewords are always freshly minted per request).

### Failure handling

Deliberately simpler than a `status = generating` placeholder row. The whole flow is synchronous,
in-process, and fast (no external I/O besides one local-disk write), so there is no benefit to a
visible intermediate DB state a user could ever observe mid-request:

- `CertificatePdfService::render()` has no side effects — it returns bytes or throws. Nothing is
  written to disk or the database if it fails.
- The PDF file is written to storage, and the `certificates` row is inserted, both inside the same
  `DB::transaction()`. If anything throws after the file was written (including exhausting
  codeword-collision retries), the `catch` block deletes that file before re-throwing, and the DB
  transaction rolls back on its own.
- Net effect: there is never a `certificates` row without a matching stored PDF, and never an
  orphaned PDF left behind on failure — verified by a test that deletes a template's background
  file out from under an in-flight issuance and asserts zero certificate rows and zero orphan
  files afterward.
- `CertificateStatus::GenerationFailed` stays in the enum (schema-readiness for a possible future
  async/queued generation path) but nothing in this flow ever writes it — a failure here is just a
  thrown exception the admin sees as a normal 500/validation error, not a certificate that exists
  in a bad state.

## Certificate number generation

Format: `{prefix}-{year}-{sequence}`, e.g. `IEEE-IUBAT-2026-000123` — prefix and zero-padding width
configurable via `config/certificates.php` (`CERTIFICATE_NUMBER_PREFIX` env var), not hardcoded.

Race-condition safety: a dedicated `certificate_number_counters` table (one row per calendar year)
is incremented via `SELECT ... FOR UPDATE` (`CertificateNumberService::next()`, called from inside
`CertificateIssuanceService`'s open transaction) — **not** `COUNT(certificates WHERE year=?) + 1`.
Counting rows gives nothing to lock against: two concurrent requests could read the same count and
mint the same number, with the DB unique constraint only catching it *after* both had already done
the expensive PDF render. The counter row's lock makes the collision structurally impossible
instead of merely detected-and-retried. Verified by a test that issues two certificates in
sequence and asserts strictly incrementing, unique numbers.

## Verification codeword generation

Phase 5's brief used the term "verification token" for this; the existing schema already had
exactly this column from Phase 2 (`certificates.codeword`, unique, documented in CLAUDE.md as "the
verification secret" — a different column from `certificate_number` for a different purpose), so
`App\Services\Certificates\VerificationCodewordService` generates directly into it rather than
adding a duplicate `verification_token` column. 32 random bytes (`random_bytes()`, PHP's CSPRNG —
never `mt_rand()`, never derived from the row id) hex-encoded to a 64-character string. Uniqueness
enforced by the DB unique constraint; `CertificateIssuanceService` retries generation (not the
whole render) up to 5 times on the rare collision.

## Snapshot strategy

Two separate JSON columns on `certificates`, both written once at issuance and never touched
again, answering two different questions for two different future readers:

- **`template_snapshot`**: *what field definitions existed* — each field's `label`, `field_key`,
  `field_type`, `is_required`, `is_recipient_name`, `options`. What the certificate detail page
  reads to label stored `data` values correctly even after a template's fields change later.
- **`layout_snapshot`**: *exactly where everything was drawn* — every field's `position`/`style`
  keyed by `field_key`, plus `certificate_number_layout`, `qr_code_layout`, and the page dimensions
  **actually used at render time** (from `PdfPageBoxReader`, not the designer's cached
  `page_width`/`page_height` columns). What a future re-render/audit feature would need.

Split into two rather than one blob because they're conceptually different (data-shape vs.
geometry) and are consumed by different code paths. Neither duplicates data blindly — `data` (the
submitted field values) stays a single existing column, not re-copied into either snapshot.

QR-only certificates (Phase 6, below) have **both snapshot columns `null`** — no PDF was ever
rendered for them, so there's nothing to snapshot. `certificates.show` falls back to the live
template's current fields for labeling `data` in that case; see §Simplified QR workflow.

## Simplified QR workflow — implemented Phase 6

**Status change**: the PDF designer and automatic PDF generation (§Certificate background &
visual layout, §PDF generation pipeline above) are **paused, not removed**. The code, tests, and
routes from Phases 4-5 all still exist and still pass; they're simply no longer the primary admin
workflow. The immediate production need turned out to be migrating an existing local-tool
workflow — dynamic form -> codeword -> QR -> manual Canva placement — rather than finishing
automatic PDF rendering. Both entry points share the same `certificates` table, the same
`CertificateNumberService`, and the same `VerificationCodewordService`; nothing about Phase 5 was
undone, and nothing here requires a template to have an uploaded PDF background at all.

### Why one table, no new certificate storage

The brief was explicit about this, and the existing Phase 2 schema already fit: `recipient_name`,
`data`, `certificate_number`, `codeword`, `status`, `issued_at`/`created_by` all mean exactly what
a QR-only certificate needs. The only schema question was whether `pdf_path` (and the two Phase 5
snapshot columns) could be null for a certificate with no PDF — they already were
(`->nullable()` since the Phase 5 migration that introduced them), so **no new migration was
needed for Phase 6** at all.

### `SimpleCertificateService`

A new, deliberately small service (`App\Services\Certificates\SimpleCertificateService`) rather
than adding a "skip the PDF" flag to `CertificateIssuanceService`: the two flows share the
number/codeword sub-services but have nothing else in common (no background, coordinates, fonts,
or PDF rendering here at all). It:

1. Confirms the template is `active`.
2. Reads the field flagged `is_recipient_name` and copies its submitted value into
   `certificates.recipient_name` (identical pattern to Phase 5's issuance service).
3. Mints a certificate number (`CertificateNumberService`) and a codeword
   (`VerificationCodewordService`), both reused unchanged from Phase 5.
4. Inserts the `certificates` row with `pdf_path`, `template_snapshot`, and `layout_snapshot` all
   `null`.

Route: `Admin\CertificateQrController` — `GET/POST /admin/certificates/generate-qr[/{template}]`.
Same dynamic-form pattern as Phase 5's issue form (`IssueCertificateRequest`, reused verbatim —
its rules are built from `$this->route('template')` regardless of which route invoked it).

### QR PNG generation

`QrCodeService` gained one new method, `pngBytes()`, alongside the Phase 5 `drawOnPdf()`. **No new
package**: `TCPDF2DBarcode::getBarcodePngData()` generates a real standalone PNG with no PDF
document involved at all — confirmed working in the Phase 6 spike, using the exact same QR
encoder Phase 5's in-PDF QR already used. The QR is generated **on demand, every request**
(`GET /admin/certificates/{certificate}/qr.png`), never written to disk — it's fully deterministic
from the verification URL (which is itself fully deterministic from the certificate's `codeword`),
so there's nothing to persist and nothing to clean up later.

The certificate detail page (`certificates/show.blade.php`) shows the QR inline, a "Download QR
PNG" link (`download` attribute, forces a save), a read-only verification-link field with a "Copy
link" button (Clipboard API, `navigator.clipboard.writeText`), and an optional "Copy QR image"
button (Clipboard API's `ClipboardItem`, feature-detected and hidden entirely in browsers that
don't support it — PNG download is the guaranteed path per the brief). The admin downloads the PNG
and places it into the existing Canva design by hand; nothing here touches Canva or produces a
final certificate image/PDF.

### Excel import

Historical data lives in multiple `.xlsx` files (often one per certificate category), and their
column headings are **not** consistent with each other or with this system's field labels — the
importer's entire reason to exist is the mapping step, not just a bulk-insert.

Routes (`Admin\CertificateImportController`, all under `/admin/certificates/import`):

```
GET  /import                       choose a category/template (ANY status — see below)
GET  /import/{template}            upload form
POST /import/{template}/upload     store file, read headers, render the mapping screen
POST /import/{template}/preview    validate every row against the chosen mapping, show counts
POST /import/{template}/errors     download a CSV of every row's error message
POST /import/{template}/confirm    re-validate, then actually write certificates rows
```

**Import is not restricted to Active templates** (unlike QR generation/live issuance) —
deliberately different from Phase 5's rule. Historical Excel data routinely belongs to an
event/category that has since been archived, and blocking import there would make migrating that
exact data impossible. A template must still have a recipient-name field configured (checked, with
a clear error, not a crash — a `draft` template that was never activated might genuinely have
none yet).

**State across steps, without a new database table**: the uploaded file is stored once, at
upload time, as `storage/app/private/imports/{uuid}.xlsx` — a server-generated UUID, never a
client-supplied path. Every subsequent step (mapping/preview/errors/confirm) carries that UUID and
the chosen column mapping through hidden form fields and re-derives everything else (headers, rows,
validation) fresh from the stored file — **confirm never trusts what preview merely echoed back**,
it re-runs the exact same `CertificateImportValidator` calculation from scratch. A persistent
`import_batches` table was considered and rejected: the whole exchange normally completes in a
handful of requests within one admin session, and the brief explicitly said not to duplicate
certificate storage — adding another table for transient upload state would be exactly that kind
of unnecessary duplication for what amounts to a few hidden `<input>` fields.

### Excel column mapping

The mapping screen lists every Excel column (with its heading and one sample value) next to a
`<select>` offering: **Ignore column**, every one of the template's assignable fields (the
recipient-flagged one is just a normal option here, labeled "(Recipient Name, required)" — there
is deliberately no separate "Recipient Name" pseudo-target; see the note in
`App\Services\Certificates\Import\ImportMappingTarget` for why an earlier version that *did* have
one was wrong), **Existing Codeword**, and **Existing Certificate Number**.

A best-effort auto-guess pre-selects obvious matches (a header containing "name" → the recipient
field, "codeword" → Existing Codeword, "certificate"+"number" → Existing Certificate Number, or an
exact case-insensitive match against a field's label) — the admin reviews and can override every
row regardless; nothing is guessed silently. No synonym dictionary was built (e.g. "Designation" →
Role, "University" → Institution) — deliberately, to avoid guessing wrong with false confidence;
the admin's manual review is the actual correctness guarantee, the auto-guess is only a
convenience for the common exact-match case.

`CertificateImportValidator::validateMapping()` blocks the import (returns to the mapping screen
with a clear message) before any row is even looked at if the recipient field isn't mapped, or if
any other *required* assignable field isn't mapped.

### Row validation

`CertificateImportValidator::validateRows()` is the single source of truth both the preview and
confirm steps call — reuses `App\Services\Certificates\TemplateFieldRules` (extracted from Phase
5's `IssueCertificateRequest` so the two entry points can never validate a field differently) for
every mapped field's type rules, plus:

- **Codeword** (if a column is mapped to it): non-empty values are checked against a permissive
  format pattern (`[A-Za-z0-9_-]{4,128}`, chosen because the old local tool's exact codeword shape
  isn't known — this rejects blanks/garbage without assuming the current 64-hex-character shape
  *new* codewords get), then checked for uniqueness against **both** the database and every other
  row already seen earlier in the same file (a duplicate can be against existing data or another
  row in the same spreadsheet — both are reported as "Duplicate codeword"). A valid, unique,
  non-empty value is **preserved** onto the new certificate row verbatim — the whole point of this
  path is that old QR codes printed on paper years ago must keep working. An empty cell (column
  mapped, but blank for that row) means "generate a new one," exactly like a certificate that never
  had a historical codeword.
- **Certificate number**: identical shape (format check via a looser pattern allowing the
  slashes/dots/spaces seen in old exports, then batch+database uniqueness, then preserve-if-valid /
  generate-if-blank).
- Every row's real Excel row number (header = row 1) is carried through to every error message, so
  "Row 32: Duplicate codeword" points at the exact row an admin would see if they opened the file.

**Partial import, explicitly** (not all-or-nothing): the preview screen's own mockup in the brief
(`Imported: 147 / Skipped: 3`) assumes it, and it's the only sane behavior for genuinely messy
historical data — one malformed row in a 150-row file shouldn't block the other 149 real
certificates. Every invalid row is skipped and reported, never imported partially or guessed at.

### Import result

`CertificateImportService::import()` processes each valid row in **its own transaction** (not one
transaction for the whole file) — a batch of "147 good, 3 bad" is the expected normal case here,
unlike Phase 5 single-issuance where any failure should produce nothing at all. One row hitting an
extremely unlikely last-moment unique-constraint race doesn't discard every other good row around
it; it's simply counted as skipped and its message included in the result page.

The result page shows exactly the fields the brief specified: Imported, Skipped, Certificates
created, Codewords preserved, New codewords generated, plus (not in the brief's mockup but the
same information for certificate numbers) Certificate numbers preserved / New certificate numbers
generated. An error-report CSV (`Row,Errors`) can be downloaded from the preview screen before
confirming — regenerated on demand from the same stored file + mapping, not a separately persisted
report file.

### Security

Same two-role boundary as everything else in the certificate system (`CertificatePolicy::create()`
— both `super_admin` and `certificate_manager`). The stored import file's UUID is validated with a
strict UUID regex before ever touching the filesystem (`CertificateImportController::
resolveStoredFile()`) — never a client-supplied path, closing the obvious path-traversal shape
this kind of "carry a file reference through a form" design invites. Dynamic form/row values are
validated server-side via the same `TemplateFieldRules` the live issuance form uses — dropdown
values, required fields, and type rules are never trusted from the client or from the spreadsheet.
`certificate_number`/`codeword` can never be set by an ordinary QR-generation form submission
(`IssueCertificateRequest` rejects any unrecognized `fields.*` key) — only the Excel import path
may explicitly preserve a *mapped* historical value, and only after the same uniqueness/format
checks every other value goes through.

## Bulk (Excel) generation — a different, still-future feature (Phase 7)

Not to be confused with §Excel import (Phase 6, above). That importer migrates **historical** data
(recipient/role/etc. + an existing codeword) with no PDF ever generated. This still-future feature
is the opposite direction: an admin fills in a **blank template this system generates**, and it
**generates a real PDF per row** (background + fields + certificate number + QR, the full Phase 5
pipeline, at bulk scale). Different input shape, different output, different phase.

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

## QR code — implemented Phase 5, verification target added Phase 6

- `App\Services\Certificates\QrCodeService::verificationUrlFor()` is the **one** URL-building
  implementation every caller uses — the in-PDF QR (Phase 5), the standalone PNG (Phase 6), the
  admin detail page's copy-link/"View Public Verification" buttons, all resolve through this single
  method, never a hand-assembled string. As of Phase 6 it calls Laravel's own `route('certificate.
  verify', ['codeword' => $certificate->codeword])` against the real public route (`routes/web.php`)
  — e.g. `http://ieee.iubat.edu/certificate/verify/<64-hex-chars>` in production, `http://localhost/
  ...` locally, always correct for the environment's actual `APP_URL` since `route()` derives it,
  not a manually concatenated config value. (Phase 5 built this method against a
  `config('certificates.verification_url_path')` placeholder before the route existed; that config
  key was removed in Phase 6 once the real route made it redundant.) Never encodes certificate data
  directly, only this URL.
- **No new package** (`endroid/qr-code`, guessed in earlier phases, was not added): TCPDF bundles
  native 2D barcode generation. `QrCodeService::drawOnPdf()` calls `TCPDF::write2DBarcode($url,
  'QRCODE,M', ...)` directly against the coordinate-converted box — a real vector shape in the PDF,
  not a rasterized image, and nothing is ever written to disk as a standalone QR file.
- Error-correction level: `M` (matches the archived prototype's choice — a reasonable default for
  printed certificates that may be photographed at an angle).
- **Phase 6 addition**: `QrCodeService::pngBytes()` — the same QR content, rendered as standalone
  PNG bytes (`TCPDF2DBarcode::getBarcodePngData()`, still zero new packages) instead of drawn into
  an open PDF document. Powers the simplified workflow's on-demand QR preview/download — see
  §Simplified QR workflow.
- **Phase 6 addition**: the public `/certificate/verify/{codeword}` route/controller now exist —
  see §Public verification below. Every QR generated by any of the three certificate-creation paths
  (manual QR form, Excel import, Phase 5 PDF issuance) resolves to it, since all three ultimately
  go through the same `verificationUrlFor()`.

## Public verification — implemented Phase 6

`GET /certificate/verify/{codeword}` (route name `certificate.verify`, `routes/web.php`, public —
no auth, not under the `admin.` prefix). See `docs/CERTIFICATE_SYSTEM.md`'s own changelog entry
below for the full writeup; summary:

- **Lookup**: `App\Services\Certificates\Verification\CertificateVerificationService::verify()`
  does exactly one query, exact match: `Certificate::where('codeword', $codeword)->first()`. Never
  certificate ID, certificate number, recipient name, or a fuzzy/partial match.
- **Route constraint**: `where('codeword', VerificationCodewordService::ACCEPTED_PATTERN)` —
  `[A-Za-z0-9_-]{4,128}`, the exact same pattern `CertificateImportValidator` checks historical
  codewords against and `VerificationCodewordService::generate()`'s own 64-hex-character output
  satisfies. One shared constant, not three independent regexes that could drift. The upper bound
  (128) exists specifically so an absurdly long junk URL 404s at the routing layer before any
  database query or view render happens.
- **Status rules** (`CertificateStatus`): `Active` -> Verified. `Revoked` -> a separate Revoked
  response (certificate number only, no recipient/dynamic data, no `revocation_reason` — the
  column/enum case already existed from Phase 2, so this display logic was built now, but no admin
  action to actually set a certificate `revoked` was built in this phase; see §Revocation below).
  Anything else (`Reissued`, `GenerationFailed`, or a future status) -> the same "Certificate Not
  Verified" response as an unknown codeword — a reissued-and-superseded certificate's original
  codeword deliberately does not claim to still be valid.
- **Public fields**: never a blind loop over `certificates.data`'s keys. Determined by
  `template_fields.show_on_verification` (+ `verification_label` override), resolved in a strict
  order — see §Snapshot/current-template fallback logic below.
- **Response object, not the model**: the controller/view never receive a `Certificate` — only
  `App\Services\Certificates\Verification\VerificationResult`, a DTO exposing only
  certificate-number/recipient-name/template-name/issued-date/public-fields (and only the fields
  each outcome actually needs). `codeword`, `id`, `created_by`, `pdf_path`,
  `template_snapshot`/`layout_snapshot` (the raw JSON), and any hidden field's value are
  structurally unreachable from the Blade view, not merely "not currently rendered."
- All values render through Blade's default `{{ }}` escaping — no `{!! !!}` anywhere on this page.
- Headers: `X-Robots-Tag: noindex, nofollow` + a `<meta name="robots" content="noindex,nofollow">`
  tag (these URLs are per-certificate secrets, never meant to be indexed), and
  `Cache-Control: no-store` (a status shown here can change later via revocation — never let a
  browser/proxy cache a stale "Verified").
- Rate-limited `throttle:60,1` (60 requests/minute/IP) — deters codeword enumeration without a
  CAPTCHA or punishing someone scanning the same QR a few times.
- No `verification_logs` table / hit-miss audit log was built — that's still Phase 9-ish future
  work per the original plan (`docs/DATABASE_DESIGN.md` §Not built yet), out of scope for "public
  verification" specifically.

### Snapshot/current-template fallback logic

`CertificateVerificationService` resolves which fields are public, and their labels, in a strict
order — documented here because getting this wrong either leaks a field that should be hidden or
makes an old certificate's public page change behavior when an admin edits the live template
later, neither of which is acceptable:

1. **`certificates.template_snapshot`** (Phase 5 PDF-path certificates always have one) — frozen at
   issuance time. `CertificateSnapshotService::templateSnapshot()` was extended in Phase 6 to also
   capture `show_on_verification`/`verification_label` per field (it previously only captured
   label/type/required/options — a real gap fixed as part of this phase, since without it there
   was no visibility data to read from a snapshot at all). A snapshot written **before** this fix
   won't have those keys — treated as `show_on_verification = false` (hide), the safer default,
   never assumed `true`.
2. **The live template's current fields** — Phase 6 QR-only (`SimpleCertificateService`) and
   Excel-imported (`CertificateImportService`) certificates both always leave `template_snapshot`
   `null` (no PDF was ever rendered for them, so there's nothing to snapshot), so this is their
   only source. A live template can still be edited after certificates exist against it (see
   §Template lifecycle) — for these certificates specifically, that means a later
   `show_on_verification` toggle *does* change what their public page shows. This is an accepted
   tradeoff, not an oversight: there is no PDF/snapshot to freeze for these certificates in the
   first place, and re-deriving from the live template is strictly better than showing nothing.
3. **Neither resolves** (defensive only — `certificate_template_id` is a `restrictOnDelete` foreign
   key, so a certificate's template can't actually be deleted out from under it) -> no dynamic
   fields shown, only the always-present base block (certificate number, recipient, category,
   issued date). Privacy wins over convenience.

The recipient-flagged field is always excluded from this dynamic-field list in both branches — its
value is already shown via the dedicated "Recipient" row; including it again as a dynamic field
too (its own `show_on_verification` value is often also `true`) produced a visibly duplicated name
in manual testing before this exclusion was added.

## Revocation

**Not built as an admin workflow in Phase 6** — no button, no form, no route to actually set a
certificate `revoked`. What Phase 6 *did* build is the verification-page display logic for that
state, because the `status` enum case and the `revoked_at`/`revocation_reason` columns already
existed from Phase 2: if a certificate's `status` is ever `revoked` (by any means — currently only
directly in the database), `/certificate/verify/{codeword}` correctly shows "Certificate Revoked"
rather than "Certificate Verified", with no recipient/dynamic data and no `revocation_reason`
exposed. The actual admin-facing revoke action (who can do it, a required reason, `audit_logs`
integration) is still Phase 8 work.

## Reissue

- Super Admin action on an existing certificate (typically one that was revoked, or needs
  corrected data).
- Creates a **new** `certificates` row: new `certificate_number`, new `codeword`, fresh `data`
  (admin can adjust values), `reissued_from_id` pointing at the original.
- Original row's `status` becomes `reissued`; it is never deleted or overwritten. Its own
  `/certificate/verify/{codeword}` (the old codeword) already, as of Phase 6, falls back to the
  same "Certificate Not Verified" response an unknown codeword gets — deliberately not a distinct
  "superseded" message (that copy/behavior can be refined in Phase 8), but the important safety
  property already holds: the original codeword can never silently keep showing "Verified" or
  start showing the new certificate's data.
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
- _(Phase 4)_ Built certificate background upload + the visual designer — see §Certificate
  background & visual layout above. Inspected the real demo Canva PDF rather than assuming
  anything about it; the non-zero `MediaBox` origin it turned out to have is why coordinate
  conversion routes through PDF.js's own viewport transform instead of a hand-rolled formula.
  Confirmed Imagick is present locally (Sail) but not in production's module list, which is why no
  server-side PDF rasterization was built. FPDI/TCPDF/Imagick are still not Composer dependencies
  of this project — Phase 4 needed none of them. QR/certificate-generation/verification sections
  above remain *planned*; nothing in this phase touched them.
- _(Phase 5)_ Built real single-certificate issuance — see §PDF generation pipeline, §Single
  certificate generation, §Certificate number generation, §Verification codeword generation,
  §Snapshot strategy, §QR code above. Two new Composer packages: `setasign/fpdi` (`^2.6`) and
  `tecnickcom/tcpdf` pinned to `^6.8` (the 7.x line is a broken fit for this stack — see §PDF
  generation pipeline). Permissions: identical two-role boundary as templates
  (`App\Policies\CertificatePolicy`, both `super_admin` and `certificate_manager` may issue/view/
  list/download; no revoke/reissue ability yet). Verified the real demo Canva PDF imports and
  renders correctly end to end (background + fields + certificate number + QR), including Bengali
  text via a newly embedded Noto Sans Bengali font — see the Phase 5 completion report for the
  rendered proof image and the full manual QA notes.
- _(Phase 6)_ Paused the PDF designer/automatic-generation work (kept in the codebase, still
  tested, no longer the primary workflow) and built the simplified QR-only workflow — see
  §Simplified QR workflow above. No schema migration needed (`pdf_path`/snapshot columns were
  already nullable from Phase 5). One new Composer package, `phpoffice/phpspreadsheet`, for Excel
  reading. Caught and fixed a real design bug during manual testing: an early version used a
  separate `_recipient_name` mapping pseudo-target, which meant a template's actual recipient
  field (identified by `is_recipient_name`) could never satisfy its own "required field must be
  mapped" check — fixed by treating the recipient field as a normal mappable field, matching how
  the live QR form already handles it.
- _(Phase 6, continued)_ Built public verification (`GET /certificate/verify/{codeword}`) — see
  §Public verification above. Fixed a real gap found while implementing this:
  `CertificateSnapshotService::templateSnapshot()` never captured `show_on_verification`/
  `verification_label`, so a Phase 5 PDF certificate's snapshot had no visibility data to read at
  all — fixed, with older snapshots (predating the fix) treated as "hide" by default, never
  "show." Fixed a second bug caught in manual curl testing: the recipient-flagged field was
  displaying twice (once via the dedicated "Recipient" row, again as its own dynamic field) when
  its `show_on_verification` also happened to be `true` — fixed by excluding the recipient field
  from the dynamic-field list in both the snapshot and live-template resolution branches. Removed
  the now-redundant `config('certificates.verification_url_path')` key: `QrCodeService::
  verificationUrlFor()` now builds the URL via Laravel's `route()` helper against the real
  `certificate.verify` route instead of string-concatenating a config value.
