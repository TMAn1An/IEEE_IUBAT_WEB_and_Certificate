# Database Design

MySQL/MariaDB, via Laravel migrations only — never hand-created in phpMyAdmin except the database
and database user themselves (`docs/DEPLOYMENT_CPANEL.md`).

**Status**: the schema below is implemented as of Phase 5 (`database/migrations/`). Columns/tables
marked *(later phase)* are intentionally not built yet — each phase stays scoped to what it
actually needs (Phase 2: database + auth/admin foundation; Phase 3: template/field management;
Phase 5: single-certificate issuance), kept lean rather than pre-building columns for features
that don't exist yet (bulk generation, verification logging). Add them in the phase that actually
needs them, and update this doc alongside that migration.

## Entity overview

```
users
certificate_templates ──< template_fields
certificate_templates ──< certificate_batches
certificate_templates ──< certificates >── certificate_batches
certificates ── (self-referencing) reissued_from_id
```

`verification_logs` and `audit_logs` (Phase 6/9) are deliberately not created yet — see
`docs/CERTIFICATE_SYSTEM.md`. There is no `events` table; the public site's event content stays in
`config/site.php` (see `docs/ARCHITECTURE.md`). `certificate_number_counters` (Phase 5, below) is a
small standalone table, not part of this relationship diagram — nothing references it by foreign
key, it's only ever read/written by `CertificateNumberService`.

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

## Not built yet (deliberately, kept lean phase by phase)

- **`verification_logs`**: privacy-conscious hit/miss log for `/verify/{codeword}` — Phase 6. The
  public verification route/controller itself don't exist yet either — see
  `docs/CERTIFICATE_SYSTEM.md` §QR code for why the QR's URL shape is already defined regardless.
- **`audit_logs`**: admin action log (template created, certificate revoked, etc.) — Phase 9, unless
  a strong reason surfaces earlier.
- Batch import/export file paths and ZIP handling — Phase 7.
- ~~Template editor coordinate/font data actually being populated in `position`/`style`~~ — done,
  Phase 4.
- ~~Certificate PDF storage path~~ / ~~`certificates.recipient_name` populated from a template's
  `is_recipient_name` field~~ / ~~certificate number + codeword generation~~ — done, Phase 5.
- Revoke/reissue *behavior* (the columns/relationships exist already) — Phase 8.

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
