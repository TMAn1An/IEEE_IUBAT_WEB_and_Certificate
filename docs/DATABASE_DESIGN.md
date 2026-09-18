# Database Design

MySQL/MariaDB, via Laravel migrations only — never hand-created in phpMyAdmin except the database
and database user themselves (`docs/DEPLOYMENT_CPANEL.md`).

**Status**: implemented through Phase 6. Two genuinely independent schema groups now exist side by
side: the **advanced system** (`certificate_templates`/`template_fields`/`certificate_batches`/
`certificates`, Phase 2-5, PDF-path, paused as the primary workflow) and the **simple QR tool**
(`qr_categories`/`qr_category_fields`/`qr_certificates`, Phase 6, rebuilt after inspecting the real
reference tool at `IEEEQRCODEGENERATOR-main/` — see `docs/CERTIFICATE_SYSTEM.md` §Simple QR tool).
Neither group has a foreign key into the other — that's deliberate, not an oversight; see that doc
section for the full reasoning. Columns/tables marked *(later phase)* are intentionally not built
yet — each phase stays scoped to what it actually needs. Add them in the phase that actually needs
them, and update this doc alongside that migration.

## Entity overview

```
users
certificate_templates ──< template_fields
certificate_templates ──< certificate_batches
certificate_templates ──< certificates >── certificate_batches
certificates ── (self-referencing) reissued_from_id

qr_categories ──< qr_category_fields
qr_categories ──< qr_certificates
qr_groups ──< qr_certificates
```

`qr_groups` has no foreign key to `qr_categories` or `CertificateTemplate` — it's an orthogonal,
automatically-populated concept (Event Type + Event Name + Role), not a schema owner. See
`docs/CERTIFICATE_SYSTEM.md` §Automatic QR grouping.

The two groups above share nothing but `users` (via `created_by`, both `restrictOnDelete`) and the
public verification route, which checks both tables by exact `codeword` match — see
`docs/CERTIFICATE_SYSTEM.md` §Public verification. `verification_logs` and `audit_logs` are
deliberately not created yet. There is no `events` table; the public site's event content stays in
`config/site.php` (see `docs/ARCHITECTURE.md`). `certificate_number_counters` (Phase 5, below) is a
small standalone table, not part of this relationship diagram — nothing references it by foreign
key, it's only ever read/written by `CertificateNumberService` (the advanced system's certificate
numbers only — the simple QR tool has no certificate-number concept at all).

## `users`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string, unique | login identifier |
| password | string | hashed (bcrypt via Laravel's default `'password' => 'hashed'` cast) |
| role | string, cast to `App\Enums\UserRole` (`super_admin`, `certificate_manager`) | plain string column at the DB level, typed at the model layer — see §Enums below |
| is_active | boolean, default `true` | soft-disable an account without deleting it; checked at login and on every admin request (`App\Http\Middleware\EnsureUserIsActive`) |
| remember_token | string, nullable | Laravel default |
| email_verified_at | timestamp, nullable | Laravel default, unused (no email verification flow in Version 1) |
| created_at / updated_at | timestamps | |

No public registration path exists anywhere in the app. Rows are created by a Super Admin via the
admin Users screen (`Admin\UserController`), or the initial account via `php artisan app:make-admin`
(see `docs/DEPLOYMENT_CPANEL.md`).

`role` and `is_active` were added in a separate migration
(`add_role_and_status_to_users_table`) on top of Laravel's default `create_users_table` migration,
rather than editing that migration in place — keeps a clean, honest migration history.

## `certificate_templates`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | e.g. "BECITHCON 2026 — Session Chair" |
| slug | string, unique | |
| description | text, nullable | |
| source_pdf_path | string, nullable | server-generated storage path to the uploaded Canva-exported PDF (Phase 4, `TemplateBackgroundService`) |
| original_filename | string, nullable | added Phase 4 — the client's filename, for display only, never used as a storage path (see `docs/SECURITY.md`) |
| file_mime | string, nullable | added Phase 4 |
| file_size | unsigned int, nullable | added Phase 4 — bytes |
| page_width | decimal(8,2), nullable | PDF page width in points; written as a side effect of the first designer "Save Layout" (Phase 4) — see `docs/CERTIFICATE_SYSTEM.md` §Coordinate system for why this isn't populated at upload time |
| page_height | decimal(8,2), nullable | PDF page height in points |
| certificate_number_layout | JSON, nullable, cast `array` | added Phase 4 — `{x, y, width, height, style}`, the certificate-number system element's layout. See `docs/CERTIFICATE_SYSTEM.md` §System-element layout storage for why this isn't a `template_fields` row |
| qr_code_layout | JSON, nullable, cast `array` | added Phase 4 — `{x, y, width, height}`, `height` always forced equal to `width` server-side (square) |
| status | string, cast to `App\Enums\CertificateTemplateStatus` (`draft`, `active`, `archived`) | draft templates can't be used for generation (enforced once generation exists); archived templates are layout-read-only as of Phase 4 |
| created_by | FK -> `users.id`, `restrictOnDelete` | |
| created_at / updated_at | timestamps | |

Indexed: `status`.

## `template_fields`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| certificate_template_id | FK -> `certificate_templates.id`, `cascadeOnDelete` | |
| label | string | shown on the (future) single-certificate form and Excel header |
| field_key | string | snake_case, unique **per template** |
| field_type | string, cast to `App\Enums\TemplateFieldType` (`text`, `long_text`, `number`, `date`, `dropdown`, `certificate_number`, `qr_code`) | |
| is_required | boolean, default `true` | |
| show_on_verification | boolean, default `true` | drives what the public `/verify/{codeword}` page displays (Phase 6) |
| is_recipient_name | boolean, default `false` | added in Phase 3 (`add_is_recipient_name_to_template_fields_table`) — see `docs/CERTIFICATE_SYSTEM.md` §Recipient-name field. At most one `true` row per template, only ever on a `text` field; enforced in `App\Services\Templates\TemplateFieldService`, not a DB constraint (no portable partial-unique-index equivalent across MySQL versions) |
| verification_label | string, nullable | overrides `label` on the verification page when set |
| options | JSON, nullable, cast `array` | dropdown choices |
| position | JSON, nullable, cast `array` | editor placement in PDF points (x/y/width/height) — see `docs/TEMPLATE_EDITOR.md`; not populated until the template editor (Phase 4) exists |
| style | JSON, nullable, cast `array` | font_family/font_size/font_weight/text_align/text_color |
| sort_order | unsigned int, default `0` | |
| created_at / updated_at | timestamps | |

Unique constraint: (`certificate_template_id`, `field_key`).

`position` and `style` are single JSON columns rather than separate `x_pt`/`y_pt`/`font_family`/etc.
columns — simpler schema, and neither is queried by column (always read/written whole, per field,
by the template editor) so there's no indexing reason to normalize them.

## `certificate_batches`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| certificate_template_id | FK -> `certificate_templates.id`, `restrictOnDelete` | |
| name | string, nullable | admin-supplied label |
| status | string, cast to `App\Enums\CertificateBatchStatus` (`pending`, `processing`, `completed`, `partial`, `failed`) | |
| total_rows | unsigned int, default `0` | |
| successful_rows | unsigned int, default `0` | |
| failed_rows | unsigned int, default `0` | |
| created_by | FK -> `users.id`, `restrictOnDelete` | |
| created_at / updated_at | timestamps | |

Indexed: `status`. Excel import/export paths, error-report path, and ZIP path/expiry
*(later phase — Phase 7, once bulk generation is built; see `docs/CERTIFICATE_SYSTEM.md`)*.

## `certificates`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| certificate_template_id | FK -> `certificate_templates.id`, `restrictOnDelete` | |
| certificate_batch_id | FK -> `certificate_batches.id`, nullable, `nullOnDelete` | null for single-generated certificates |
| certificate_number | string, **unique** | human-readable, e.g. `IEEE-IUBAT-2026-000123`; generated by `CertificateNumberService` (Phase 5) |
| codeword | string, **unique** | verification secret — what Phase 5's brief calls the "verification token"; 64 hex characters from `random_bytes(32)`, generated by `VerificationCodewordService` (Phase 5) |
| recipient_name | string, nullable | denormalized for admin search/listing — copied at issuance time from the template's `is_recipient_name` field (Phase 5); `data` stays authoritative |
| data | JSON, cast `array` | `field_key => value` for every `template_fields` row on this template at generation time — populated Phase 5 |
| pdf_path | string, nullable | added Phase 5 — private-disk path to the generated PDF (`certificates/{year}/{uuid}.pdf`), served only via `Admin\CertificateController::download()` |
| template_snapshot | JSON, nullable, cast `array` | added Phase 5 — field definitions (label/key/type/options/is_recipient_name) as they existed at issuance time. See `docs/CERTIFICATE_SYSTEM.md` §Snapshot strategy |
| layout_snapshot | JSON, nullable, cast `array` | added Phase 5 — exactly where everything was drawn: per-field position/style, system-element layouts, and the page dimensions actually used |
| status | string, cast to `App\Enums\CertificateStatus` (`active`, `revoked`, `reissued`, `generation_failed`), default `active` | `generation_failed` added Phase 5 for schema-readiness only — the synchronous Phase 5 issuance flow never actually writes it (see `docs/CERTIFICATE_SYSTEM.md` §Failure handling) |
| issued_at | timestamp, nullable | set at issuance (Phase 5) |
| revoked_at | timestamp, nullable | |
| revocation_reason | text, nullable | |
| reissued_from_id | FK -> `certificates.id` (self-referencing), nullable, `nullOnDelete` | set on the **new** record when it supersedes an older one |
| created_by | FK -> `users.id`, `restrictOnDelete` | the issuing admin (Phase 5) |
| created_at / updated_at | timestamps | |

Indexed: unique(`codeword`), unique(`certificate_number`), `status`, plus the automatic indexes
Laravel adds for each `foreignId()`/`constrained()` column (`certificate_template_id`,
`certificate_batch_id`, `reissued_from_id`, `created_by`).

Reissue mechanics (model/schema ready, behavior not implemented yet): original row's `status`
becomes `reissued` (never deleted); a new row is inserted with a fresh `certificate_number`/
`codeword` and `reissued_from_id` pointing at the original. `Certificate::reissuedFrom()` /
`reissuedTo()` model relationships are already in place for this.

## `certificate_number_counters`

| Column | Type | Notes |
|---|---|---|
| year | unsigned smallint, **primary key** | |
| next_sequence | unsigned int, default `1` | the next value `CertificateNumberService` will hand out for this year |
| created_at / updated_at | timestamps | |

One row per calendar year, created on first use. `CertificateNumberService::next()` reads the row
with `SELECT ... FOR UPDATE` (row lock) inside the issuance transaction, then increments it — the
lock is what makes certificate-number generation race-condition safe under concurrent issuance; a
plain `COUNT(certificates) + 1` has nothing to lock against. See
`docs/CERTIFICATE_SYSTEM.md` §Certificate number generation.

## `qr_categories`

The simple QR tool's category system — Phase 6, fully independent of `certificate_templates` (no
foreign key either direction). See `docs/CERTIFICATE_SYSTEM.md` §Simple QR tool.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | e.g. "BECITHCON 2026" |
| slug | string, unique | |
| event_name | string, nullable | the old tool's "Conference/Event" value, e.g. "IEEE BECITHCON 2026" — fixed per category rather than a per-submission toggle (a deliberate simplification from the real old tool; see docs/CERTIFICATE_SYSTEM.md §Known differences) |
| description | text, nullable | |
| is_active | boolean, default `true` | a plain toggle — no draft/active/archived lifecycle, no activation-validation gate like `certificate_templates.status` |
| created_by | FK -> `users.id`, `restrictOnDelete` | |
| created_at / updated_at | timestamps | |

Indexed: `is_active`.

## `qr_category_fields`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| qr_category_id | FK -> `qr_categories.id`, `cascadeOnDelete` | |
| label | string | |
| key | string | snake_case, unique **per category** — mirrors `template_fields.field_key` |
| type | string, cast to `App\Enums\QrCategoryFieldType` (`text`, `long_text`, `number`, `date`, `dropdown`) | smaller than `TemplateFieldType` — no `certificate_number`/`qr_code` system-field cases, since this tool has no PDF/layout concept for them to describe |
| required | boolean, default `true` | |
| options | JSON, nullable, cast `array` | dropdown choices |
| sort_order | unsigned int, default `0` | |
| is_recipient_name | boolean, default `false` | same "at most one per category" rule as the advanced system, enforced in `App\Services\QrTool\QrCategoryFieldService`, not a DB constraint |
| show_on_verification | boolean, default `true` | drives the public verification page — read from the LIVE row at verification time, always (no snapshot exists for this table; see below) |
| created_at / updated_at | timestamps | |

Unique constraint: (`qr_category_id`, `key`). No `position`/`style` JSON columns — there is no PDF
placement concept anywhere in this tool.

## `qr_certificates`

The simple QR tool's own record table — Phase 6, deliberately NOT the advanced system's
`certificates` table (whose `certificate_template_id` is a required, `restrictOnDelete` foreign
key that would have forced every simple record to depend on `CertificateTemplate`).

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| qr_category_id | FK -> `qr_categories.id`, `restrictOnDelete` | still the field-*schema* owner |
| qr_group_id | FK -> `qr_groups.id`, nullable, `nullOnDelete` | the auto-resolved Event Type + Event Name + Role group this record belongs to — see `docs/CERTIFICATE_SYSTEM.md` §Automatic QR grouping. Nullable at the DB level, but always populated by the application (`QrCertificateIssuanceService`/`QrCategoryImportService`) for every record created going forward |
| recipient_name | string | copied from whichever field is flagged `is_recipient_name`, same pattern as the advanced system |
| event_name | string, nullable | denormalized **per record**, always taken from the resolved `qr_group_id`'s `event_name` (never a raw per-row value) — frozen per record without needing a full snapshot system |
| data | JSON, cast `array` | `key => value` for every category field, dynamic per category |
| codeword | string, **unique** | 16 characters, uppercase A-Z/0-9 — matches the real old tool's format exactly (`secrets.choice(string.ascii_uppercase + string.digits)` × 16 in the original Python), generated by `App\Services\QrTool\QrToolCodewordService` using PHP's CSPRNG. A deliberately different shape from the advanced system's 64-character lowercase-hex codeword — both are accepted by the same public verification route without any special-casing (`VerificationCodewordService::ACCEPTED_PATTERN` already covers both) |
| status | string, cast to `App\Enums\QrCertificateStatus` (`active`, `revoked`), default `active` | smaller than `CertificateStatus` — no `reissued`/`generation_failed`, neither concept exists in this tool |
| created_by | FK -> `users.id`, `restrictOnDelete` | |
| created_at / updated_at | timestamps | `created_at` is explicitly overridable on Excel import, to preserve a historical row's original "Created At" value from the old tool's Excel export |

Indexed: unique(`codeword`), `status`. No `certificate_number` column — the old tool never had one
("SL" was a per-Excel-file row counter, not a portable identifier). No `pdf_path`,
`template_snapshot`, or `layout_snapshot` — no PDF is ever rendered, and (unlike the advanced
system) this table does not snapshot field definitions at issuance at all; public field visibility
is always resolved from the category's live fields. See `docs/CERTIFICATE_SYSTEM.md` §No snapshot
for the reasoning.

## `qr_groups`

Restores the old tool's automatic per-Excel-file separation (one file per Event Type + Conference
Name + Role combination) as a database concept — see `docs/CERTIFICATE_SYSTEM.md` §Automatic QR
grouping. Never created via a dedicated admin form of its own fields; always via
`App\Services\QrTool\QrGroupService::resolve()`'s find-or-create, called automatically from the
Generate QR page and the group-based importer.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| event_type | string | e.g. "Conference", "Event", or the literal "No Conference" when the submission left "Include conference/event" unchecked |
| event_name | string | e.g. "IEEE BECITHCON 2026" |
| role | string | e.g. "Session Chair" |
| group_key | string, **unique** | normalized (trimmed, whitespace-collapsed, lowercased) `event_type` + `event_name` + `role`, joined with the ASCII "unit separator" control character — the actual uniqueness constraint, kept as a real indexed column so find-or-create is one lookup, not a scan |
| is_active | boolean, default `true` | reserved for a future "deactivate a stale group" action; not yet exposed in any admin UI |
| created_at / updated_at | timestamps | |

Deliberately **not** the same concept as `qr_conference_types`/`qr_conference_options` (curated,
selectable dropdown OPTIONS) or a category field's `options` JSON (role dropdown OPTIONS) — those
are values an admin adds ahead of time; a group is an automatically created *combination* of
whichever values were actually submitted or imported.

## `qr_conference_types` / `qr_conference_options`

Persists what the old tool's "Add type option"/"Add conference name" buttons kept only in the
browser's `localStorage` — global lists (not per-category), matching the old tool having exactly
one such pair of lists. See `docs/CERTIFICATE_SYSTEM.md` §Persisted option lists.

| Table | Column | Notes |
|---|---|---|
| `qr_conference_types` | id, name (unique), timestamps | e.g. "Conference", "Event" |
| `qr_conference_options` | id, qr_conference_type_id (FK, `cascadeOnDelete`), name, timestamps | e.g. "IEEE BECITHCON 2026" under "Conference" |

Unique constraint on `qr_conference_options`: (`qr_conference_type_id`, `name`). Removing a type or
option never touches `qr_certificates` — that table stores the resolved name as a plain string in
`event_name`/`data`, with no foreign key back to either table.

## Enums (`App\Enums\*`)

Every `status`/`role`/`field_type` column is a plain `string` at the database level, cast to a PHP
backed enum at the Eloquent model layer (`protected function casts(): array`) — Laravel's current
recommended approach, not a MySQL-native `ENUM` column type. Reasons: portable across MySQL
versions/MariaDB, no `ALTER TABLE` needed to add a case later, and the PHP enum gives IDE
autocompletion + a `label()` method for display text in one place.

- `UserRole`: `SuperAdmin`, `CertificateManager`
- `CertificateTemplateStatus`: `Draft`, `Active`, `Archived`
- `TemplateFieldType`: `Text`, `LongText`, `Number`, `Date`, `Dropdown`, `CertificateNumber`, `QrCode`
- `CertificateBatchStatus`: `Pending`, `Processing`, `Completed`, `Partial`, `Failed`
- `CertificateStatus`: `Active`, `Revoked`, `Reissued`, `GenerationFailed`
- `QrCategoryFieldType`: `Text`, `LongText`, `Number`, `Date`, `Dropdown` (simple QR tool)
- `QrCertificateStatus`: `Active`, `Revoked` (simple QR tool)

## Not built yet (deliberately, kept lean phase by phase)

- **`verification_logs`**: privacy-conscious hit/miss log for `/certificate/verify/{codeword}` —
  still future work. Public verification itself (the route, lookup, and display logic) shipped in
  Phase 6 *without* this table; add it later if usage tracking becomes a real need.
- **`audit_logs`**: admin action log (template created, certificate revoked, etc.) — Phase 9, unless
  a strong reason surfaces earlier.
- Batch import/export file paths and ZIP handling — Phase 7.
- ~~Template editor coordinate/font data actually being populated in `position`/`style`~~ — done,
  Phase 4.
- ~~Certificate PDF storage path~~ / ~~`certificates.recipient_name` populated from a template's
  `is_recipient_name` field~~ / ~~certificate number + codeword generation~~ — done, Phase 5.
- ~~Public verification route/lookup/display~~ — done, Phase 6 (`CertificateStatus::Revoked` is now
  actually read by that display logic; setting it is still Phase 8 work, see below).
- Revoke/reissue *admin-facing action* (the `status`/`revoked_at`/`revocation_reason` columns and
  the `CertificateStatus::Revoked` enum case already exist and are already read by verification) —
  Phase 8.

## Design decisions worth recording

- **Not over-normalized on purpose**: `data` as JSON on `certificates` avoids an EAV table
  explosion (a `certificate_values` table with one row per field per certificate) while staying
  fully dynamic. `template_fields` is the normalized, queryable schema; `certificates.data` is the
  per-record payload validated against it at write time (validation logic: Phase 5).
- **No separate `events` table for v1**: the public site's event content (BECITHCON, HTA
  exhibition) stays in `config/site.php`, since it isn't part of the certificate domain and the
  original site already treated it as config, not database rows.
- **`recipient_name` is denormalized deliberately**: template fields are dynamic, so there's no
  guaranteed "name" column to query/sort/search by otherwise. Populated (Phase 5) from whichever
  field a template marks as the recipient-name field, read directly in
  `CertificateIssuanceService::issue()`.
- **Foreign key delete behavior**: `restrictOnDelete` on every `created_by`/template/batch
  reference (can't delete a user/template/batch that still has dependent rows — there's no user or
  template *deletion* feature anyway, only deactivation/archiving) and `nullOnDelete` on the two
  genuinely-optional links (`certificate_batch_id`, `reissued_from_id`).
