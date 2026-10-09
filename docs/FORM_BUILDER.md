# Dynamic Form Builder

A general-purpose form system inside the admin dashboard: authorized staff visually build and
style forms, publish them at `/forms/{slug}`, and review/export the submissions. It is
**independent of the certificate and QR systems**: there are no foreign keys between their tables and
these, and no code in either system calls into the other. The only shared infrastructure is the
existing Logbook (`audit_logs`) and the project-wide `ExcelFormulaGuard`.

Admin nav: **Forms → All Forms / Create Form / Submissions**.

## Contents

1. [Database schema](#database-schema)
2. [Builder architecture](#builder-architecture)
3. [Field types](#field-types)
4. [Layout system](#layout-system)
5. [Style system](#style-system)
6. [Custom CSS handling](#custom-css-handling)
7. [Custom HTML sanitization](#custom-html-sanitization)
8. [Custom code security](#custom-code-security)
9. [Conditional logic](#conditional-logic)
10. [Public form route](#public-form-route)
11. [Server-side validation](#server-side-validation)
12. [Submission architecture](#submission-architecture)
13. [Historical snapshot strategy](#historical-snapshot-strategy)
14. [Excel export](#excel-export)
15. [Authorization](#authorization)
16. [Audit integration](#audit-integration)
17. [Save reliability](#save-reliability)
18. [Extending: adding a field type](#extending-adding-a-field-type)
19. [Known limitations](#known-limitations)

## Database schema

| Table | Purpose |
|---|---|
| `forms` | One row per form. `name`, unique `slug`, `description`, `status` (`App\Enums\FormStatus`: `draft`/`active`/`inactive`/`archived`), `settings` JSON, `style_settings` JSON, `custom_css`, `custom_html_before`, `custom_html_after`, `custom_js` (stored only, never rendered), `lock_version` (optimistic-concurrency counter), `published_at`, `created_by`, `updated_by`. |
| `form_fields` | One row per builder element. `form_id`, `label`, `key` (unique per form **including archived fields**), `type` (`App\Enums\FormFieldType`), `required`, `sort_order`, `settings` JSON, `style_settings` JSON, `conditional_rules` JSON, `is_active` (false = archived). |
| `form_submissions` | One row per accepted submission. `form_id`, `submitted_by` (nullable — public visitors), `submitted_at`, `form_version` (the form's `lock_version` at submission time), `metadata` JSON (IP, user agent). |
| `form_submission_values` | One row per stored value. `form_submission_id`, `form_field_id` (nullable, `nullOnDelete`), `field_key`, `field_label_snapshot`, `field_type_snapshot`, `value` (JSON-encoded: string, list or bool), `display_value` (human-readable snapshot), `created_at` only. Unique (`form_submission_id`, `field_key`). |

Every foreign key from forms/fields/submissions uses `restrictOnDelete`, and no route deletes any of
these rows: forms are archived, fields with submissions are archived, and submissions are permanent.

The JSON columns are never free-form: each has a PHP "schema" class that owns its allowed keys,
validation and defaults (below). `forms.settings.schema_version` is stamped on every save so a future
change to the JSON shape can be migrated deliberately.

## Builder architecture

```
Browser (public/js/admin/form-builder.js)             Server
─────────────────────────────────────────             ──────────────────────────────────────────────
boots from #form-builder-data  ◄───────────────────── FormBuilderState::forForm() + meta()
  (definition + schemas + permissions)                   (enums/schemas → palette, panels, operators)
holds the whole definition in `state`
renders a live preview with the SAME markup/classes
  as the public renderer + public/css/forms.css
PUT /admin/forms/{form}/builder (full definition) ──► SaveFormDefinitionRequest
                                                         rules()  ← FormDefinitionValidator::rules()
                                                         after()  ← FormDefinitionValidator::after()
                                                       FormDefinitionValidator::normalize()  (whitelist)
                                                       FormBuilderService::saveDefinition()  (1 transaction)
◄──────────────────────────── 200 {state} │ 422 {errors} │ 409 stale version
POST /admin/forms/{form}/builder/sanitize ──────────► FormCssScoper / FormHtmlSanitizer (live preview only)
```

- **Three-column screen**: palette (left) → canvas (center) → settings panel with tabs
  *Field / Design / Settings / Custom code* (right).
- **Adding**: click a palette item (inserts below the selected field) or drag it onto the canvas.
- **Reordering**: ↑/↓ buttons on each field, **Alt+↑ / Alt+↓** on a focused field, or drag-and-drop.
  Buttons and keyboard are always available; drag is optional.
- **Duplicate / remove**: on each field's hover bar and at the bottom of the Field tab. Removing a
  field that has submissions *archives* it (listed under the canvas with a Restore button).
- **Keys**: generated from the label (`Full name` → `full_name`) until the admin edits the key; locked
  once the field has submissions. Renaming a key also rewrites every condition that referenced it.
- **Saving**: *Save draft* / *Publish* (draft & deactivated forms) or *Save changes* (live forms).
  The payload is the complete ordered definition, archived fields included; any stored field missing
  from it is deleted (no submissions) or archived (has submissions).
- **Autosave**: drafts only, 5 seconds after the last change. It never runs on a published form, so a
  half-finished edit can't go live by accident, and it is never the only way to save. Autosaves are not
  individually written to the Logbook; the next explicit save is.
- **Error handling**: 422 errors are mapped back onto the offending field (red outline + list in the
  Field tab) or tab (dot on the tab label); 409 (someone else saved first), 419 (session expired) and
  403 are shown with instructions. Unsaved changes trigger a "leave page?" warning.

Server classes (all in `app/Services/Forms/`):

| Class | Responsibility |
|---|---|
| `FormBuilderService` | create, saveDefinition, publish/deactivate/archive/restore, duplicate |
| `FormDefinitionValidator` | rules, cross-field checks, normalization of a builder payload |
| `FieldSettingsSchema` / `FormSettingsSchema` | per-field and form-level behavior settings |
| `Style\FormStyleSchema` / `Style\FieldStyleSchema` | design settings → validation → CSS custom properties |
| `FormHtmlSanitizer` / `FormCssScoper` | custom HTML / custom CSS safety |
| `FormVisibilityResolver` | conditional logic, server side |
| `FormAvailability` | "can this visitor submit right now?" |
| `FormSubmissionService` | validate + store a submission |
| `FormSubmissionExportService` | Excel export |
| `FormPresenter` / `FormBuilderState` | view data for the renderer / the builder |
| `FormAuditLogger` | writes Logbook entries |

## Field types

`App\Enums\FormFieldType` is the single source of truth for what each type is and which settings it
accepts (`settingKeys()`); the builder receives the same metadata (`builderMeta()`).

| Group | Types | Stores a value? |
|---|---|---|
| Inputs | `text`, `long_text`, `email`, `number`, `phone`, `date`, `time`, `datetime`, `hidden` | yes (`hidden`: server-set) |
| Choices | `select` (dropdown), `radio`, `checkbox_group`, `checkbox` (single) | yes |
| Layout & content | `heading`, `paragraph`, `divider`, `section`, `html` | no |

Field settings (only those valid for the type are accepted): label, key, required, placeholder, help
text, default value, width, CSS class, min/max (numbers or date/time bounds), step, max length, pattern
(+ custom error message), rows, options (label + stored value), option layout, checkbox text, read-only,
disabled, "show in submissions list", content (heading/paragraph/section/HTML), heading size, alignment.

- **Read-only**: visitors see the default value; the server always stores the default, whatever is posted.
- **Disabled**: shown greyed out; never submitted or stored.
- **Hidden field**: stores its configured value; a posted value is ignored.
- **Section**: a titled divider whose conditional visibility applies to every field after it, up to the
  next section.

## Layout system

Each field has a width of 100 / 75 / 66 / 50 / 33 / 25 %. The renderer uses a 12-column CSS grid
(`.ff-grid`, widths map to `span 12/9/8/6/4/3`), so fields sit side by side until a row is full. Below
640 px every field spans the full width. There is no free-form grid editor: widths plus order are reliable
and cover side-by-side layouts.

## Style system

Form-level design (`forms.style_settings`) is grouped into **Form container** (max width, alignment,
background, text color, font, padding, field spacing, border width/color, corner radius, shadow),
**Labels** (color, size, weight, spacing), **Inputs** (text/background/border/focus colors, border
width, radius, padding, font size), **Submit button** (text, background, hover, text color, radius,
size, padding, width, alignment) and **Errors & messages** (error text/border, success
background/text/border).

Every property is one of three kinds, defined in `FormStyleSchema::definition()`:

- **color**: `#rrggbb` only (what `<input type="color">` produces); validated with a regex.
- **px**: an integer within the property's min..max, emitted as `{n}px`.
- **enum**: a key from a fixed map. The CSS value emitted is the server-side map value, never the submitted string.

Each property becomes a CSS custom property (e.g. `--ff-label-color`) on `#ff-form-{id}`, and
`public/css/forms.css` uses those variables. The same stylesheet renders the builder canvas and the
public page, so the preview is exact.

Per-field overrides (`form_fields.style_settings`, `FieldStyleSchema`) re-declare the same
variables on that field's wrapper (`style="--ff-label-color:#…"`), so they cascade to that field only.
They are optional: empty means "inherit the form design".

Values are validated on save **and** re-normalized at render time, so an invalid value that reached the
database by some other route still never reaches CSS.

## Custom CSS handling

Super Admin only. Stored exactly as typed, and transformed at **render time** by `FormCssScoper` (so
improving the scoper re-protects every existing form without a data migration):

- every selector gets `#ff-form-{id}` prepended; `html` / `body` / `:root` at the start of a selector are
  replaced *by* the wrapper (`body { … }` styles the form box, not the page);
- `@media` / `@supports` / `@container` are kept and their rules scoped recursively; `@keyframes` kept;
- every other at-rule (`@import`, `@font-face`, `@page`, `@layer`, …) is dropped;
- nested rule blocks are dropped (declarations only), so CSS nesting can't escape the scope;
- `<` is escaped (no `</style>` break-out) and `expression(`, `javascript:`, `behavior:`, `-moz-binding`
  are neutralized;
- max 20 000 characters.

## Custom HTML sanitization

HTML-block fields (any admin) and custom HTML before/after the form (Super Admin) go through
`FormHtmlSanitizer`, a wrapper around **symfony/html-sanitizer** (a DOM-parsing allowlist sanitizer;
see `docs/ARCHITECTURE.md` §6). It is applied on save **and** on every render.

- Allowed: headings h2–h6, `p br hr div span strong b em i u s small mark sub sup abbr blockquote q code
  pre ul ol li dl dt dd a img figure figcaption table caption thead tbody tfoot tr th td`, with `class`,
  `title`, `lang`, `dir` plus a few element-specific attributes (`href`, `target`, `src`, `alt`,
  `width`, `height`, `colspan`, …).
- Removed with their content: `script`, `style`, `iframe`, `object`, `embed`, form controls, `svg`,
  `math`, `template`, media elements, …
- Unknown tags are unwrapped (their text is kept).
- Never allowed: `on*` event handlers, the `style` attribute, and URL schemes other than
  http/https/mailto/tel, so `javascript:` and `data:` never survive. Every link gets
  `rel="noopener noreferrer nofollow"`.

## Custom code security

| Item | Who | Behavior |
|---|---|---|
| Custom CSS | Super Admin | stored; rendered **scoped** (above) |
| Custom HTML before/after | Super Admin | stored **sanitized**; re-sanitized on render |
| Custom JavaScript | Super Admin | **stored only, never output or executed** anywhere (not on the public page, not in the builder) |
| HTML-block field | any form editor | sanitized like custom HTML |

The four custom-code keys are `prohibited` in the save request for anyone but a Super Admin (422 if
present), and the builder never sends the raw code to other users. They see a lock notice, but the
preview still applies the already-scoped/sanitized result. Enabling custom JavaScript requires a
separate, security-reviewed phase (sandboxing, CSP, review workflow); until then the field exists
only so that work isn't blocked by schema changes.

## Conditional logic

Stored on a field as:

```json
{"action": "show", "match": "all",
 "conditions": [{"field": "department", "operator": "equals", "value": "other"}]}
```

- `action`: `show` | `hide`; `match`: `all` | `any`; up to 10 conditions.
- Operators: `equals`, `not_equals`, `contains`, `is_empty`, `is_not_empty`. Comparison is trimmed and
  case-insensitive; for a checkbox group, `equals`/`contains` mean "one of the ticked options is".
  A single checkbox is `"1"` when ticked.
- A condition may only reference an **active, visitor-filled field placed above** the field (validated
  on save). This makes cycles impossible and evaluation a single top-to-bottom pass.
- A hidden field's value counts as empty for later conditions.
- A section's visibility applies to every field up to the next section.

The same algorithm exists twice and must stay identical:
`App\Services\Forms\FormVisibilityResolver` (server, authoritative) and `public/js/forms/form-logic.js`
(browser: public page + builder preview). On the public page hidden fields are hidden *and* their
inputs disabled; the server re-evaluates every submission regardless.

## Public form route

`GET|POST /forms/{slug}` (`App\Http\Controllers\FormController`, POST throttled to 30/min/IP). The page
uses the normal site layout (IEEE header/footer untouched). A form is available only when
`FormAvailability` says so:

- status must be **active** (draft, deactivated and archived forms return 404);
- `visibility = private` forms require a signed-in, active admin user (404 otherwise);
- `opens_at` / `closes_at` (site timezone), `submission_limit` and "allow multiple submissions" show a
  friendly message instead of the form.

After a successful submission the visitor sees the success message, or is redirected to the configured
`redirect_url` (http/https only, validated on save). Admins can preview any form, including drafts, at
`/admin/forms/{id}/preview` (same renderer, submission disabled).

## Server-side validation

`FormSubmissionService` rebuilds the rules from the **stored** field definitions on every request:

1. Any posted key that isn't an active value-collecting field is rejected (`form` error).
2. Visibility is resolved from the posted values. Hidden-by-condition fields are not required, not
   validated and not stored, whatever the browser sent.
3. Per-type rules: `email:rfc`; `numeric` + min/max; `date_format` (+ min/max bounds) for date, time and
   datetime; a phone character whitelist; `max` length (default 1 000 / 10 000); optional anchored
   `regex` pattern; dropdown/radio `Rule::in(option values)`; checkbox group `array` + each item
   `in` + `distinct`; required single checkbox `accepted`.
4. Hidden and read-only fields take their value from the definition; disabled fields are skipped.
5. Inside a transaction with the form row locked, availability (limit, schedule) is re-checked, then
   the submission and its values are written.

## Submission architecture

`form_submissions` + `form_submission_values` (one row per visible value-collecting field, empty answers
stored as null). Metadata: IP address and user agent (255 chars), and `form_version`.

Admin pages:
- **Forms → Submissions**: every form with its submission count and latest submission date.
- **Per-form list**: ID, submitted at, and up to three summary fields (those ticked "Show in submissions
  list", else the first three). Wide forms are never forced into one giant table.
- **Detail page**: every stored value with its snapshot label and key, plus a note when the field has
  since been renamed, archived or removed. Route-model binding is `scopeBindings()`: a submission id
  from another form returns 404.

There is no edit or delete action for submissions.

## Historical snapshot strategy

A submission must stay understandable whatever happens to the form later. At submission time each value
row stores:

- `field_key`, `field_label_snapshot`, `field_type_snapshot`;
- `value` (raw: option *values*, a list for checkbox groups, a bool for single checkboxes);
- `display_value`: what a human should read (option **labels**, `Robotics, AI`, `Yes`/`No`).

Live-definition rules that protect this:
- a field's **key** can't change once it has submissions;
- a field with submissions is **archived**, never deleted, and its key stays reserved (a new field can't
  reuse it);
- option label/value edits never touch stored rows, because `display_value` already holds the label as it was.

## Excel export

`GET /admin/forms/{form}/submissions/export.xlsx` (`FormSubmissionExportService`, PhpSpreadsheet):

- one row per submission (`Submission ID`, `Submitted At`, `Submitted By`, then fields);
- one column per value-collecting field of the form, active **and** archived (`Nickname (archived)`),
  in builder order, headed by the field's current label. Duplicate labels get `[key]` appended;
- values are mapped to columns by `form_field_id`, so a renamed field keeps its column and old values.
  Values whose field row no longer exists fall back to a column keyed by `field_key`
  (`… (removed)`);
- cells hold `display_value`, are written as explicit strings (leading zeros survive), and pass
  through `ExcelFormulaGuard` (values starting with `= + - @` get a leading `'`), headings included;
- submissions are read with `chunkById(500)`, not all at once. The workbook itself is held in memory by
  PhpSpreadsheet, which is fine for thousands of rows. See Known limitations for very large exports.

## Authorization

`App\Policies\FormPolicy`, enforced in controllers and Form Requests (never just by hiding buttons):

| Ability | super_admin | certificate_manager |
|---|---|---|
| list / view / preview / create / edit / publish / deactivate / duplicate | ✓ | ✓ |
| view & export submissions | ✓ | ✓ |
| archive / restore a form | ✓ | – |
| Custom Code (CSS, HTML before/after, JS) | ✓ | – |
| edit an **archived** form | – | – |
| delete a form or a submission | – (no route exists) | – |

All admin routes sit behind `auth` + `active`. Field ids in a save payload must belong to the form
being saved (`FormDefinitionValidator::after()`), and every write goes through whitelisting normalizers.
No model attribute is ever filled from raw request input.

## Audit integration

Form-builder actions are written to the existing append-only `audit_logs` table (the Logbook), not to a
second audit system. They use `record_type = form`, a new audit-only case on
`App\Enums\DeletableRecordType` whose `isDeletable()` is false (`DeletionRequestService::request()`
refuses it). Events (`App\Enums\AuditEventType`): `form_created`, `form_updated` (metadata: added/
deleted/archived field keys, field count), `form_published`, `form_deactivated`, `form_archived`,
`form_restored`, `form_duplicated` (metadata: source form id). Each entry snapshots the form's name,
slug, status and version.

## Save reliability

- Every save is one transaction with the form row locked (`lockForUpdate`). Fields are deleted/archived,
  renamed keys are parked on a temporary value (so swapping two keys can't trip the unique index), fields
  are upserted in order, and the form row is updated last. A failure rolls back everything.
- **Optimistic locking**: the builder sends the `lock_version` it loaded. A stale version gets 409, so
  two admins (or two tabs) can't silently overwrite each other.
- Order is taken from the payload's numeric indexes (fields, options and conditions). Laravel's
  `validated()` rebuilds arrays in rule order, so the normalizers `ksort` by index. A regression test
  covers this.
- Reloading the builder shows exactly what was stored: fields, order, widths, options, design, colors,
  conditions, custom CSS and HTML blocks.

## Extending: adding a field type

1. Add a case to `FormFieldType` (label, group, `settingKeys()`, and if relevant `htmlInputType()` /
   `dateFormat()`).
2. Add validation for any new setting key in `FieldSettingsSchema`.
3. Render it in `resources/views/forms/_renderer.blade.php` and in `renderFieldBody()` in
   `public/js/admin/form-builder.js` (same classes).
4. Add its submission rules in `FormSubmissionService::rulesFor()` and, if needed, its display format
   in `displayValue()`.
5. Tests.

File upload, address, rating, signature and repeater fit this shape. File upload additionally needs
private storage plus a download route, and a repeater needs a nested value shape. Neither is built.

## Known limitations

- **Custom JavaScript is stored but never executed** (by design for this phase).
- "Allow multiple submissions = off" is best-effort for anonymous visitors (per browser session); it is
  per account for signed-in users. It is not an identity check.
- No CAPTCHA or honeypot yet; spam protection is the per-IP throttle only.
- No full revision history of form definitions. Submissions record `form_version`, and the Logbook
  records each save, but old definitions aren't stored.
- No file upload, payment, signature, multi-page wizard, calculations, webhooks or external
  integrations.
- Option *value* edits are not propagated into conditions that compare against the old value; the
  builder shows the condition's value select, so re-pick it after renaming a value.
- Very large exports (tens of thousands of rows) are limited by PhpSpreadsheet holding the workbook in
  memory. Switch to a streaming writer if that ever becomes real.
- Changing the slug of a published form breaks links already shared (the builder warns).
