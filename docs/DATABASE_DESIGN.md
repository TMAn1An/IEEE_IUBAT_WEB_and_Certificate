# Database Design

MySQL/MariaDB, via Laravel migrations only — never hand-created in phpMyAdmin except the database
and database user themselves (`docs/DEPLOYMENT_CPANEL.md`).

## Entity overview

```
users
certificate_templates ──< template_fields
certificate_templates ──< certificate_batches
certificate_templates ──< certificates >── certificate_batches
certificates ──< verification_logs
certificates ── (self-referencing) reissued_from_id
users ──< audit_logs (nullable, for system actions)
```

## `users`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string, unique | login identifier |
| password | string | hashed (bcrypt/argon2 via Laravel defaults) |
| role | string/enum: `super_admin`, `certificate_manager` | see `App\Enums\UserRole` |
| remember_token | string, nullable | |
| is_active | boolean, default true | soft-disable an account without deleting it |
| created_at / updated_at | timestamps | |

No public registration path exists anywhere in the app. Rows are created by a Super Admin via the
admin Users screen, or the initial account via `php artisan app:make-admin` (documented in
`docs/DEPLOYMENT_CPANEL.md`).

## `certificate_templates`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | e.g. "BECITHCON 2026 — Session Chair" |
| slug | string, unique | for admin URLs; never exposed publicly as an identifier for a specific certificate |
| description | text, nullable | |
| pdf_disk | string, default `local` | which filesystem disk the source PDF lives on |
| pdf_path | string | path under `storage/app/private/certificate-templates/{id}/original.pdf` |
| page_width_pt | decimal | PDF page width in points, read from the uploaded PDF at upload time |
| page_height_pt | decimal | PDF page height in points |
| page_orientation | string: `portrait` \| `landscape` | derived from width/height, stored for convenience |
| status | string/enum: `draft`, `active`, `archived` | draft templates can't be used for generation |
| created_by | FK -> users.id | |
| created_at / updated_at | timestamps | |

A template's fields, dimensions and PDF are the **only** thing that differs between certificate
types. Nothing certificate-type-specific ever lives outside this table and `template_fields`.

## `template_fields`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| template_id | FK -> certificate_templates.id, cascade delete | |
| field_key | string | snake_case, e.g. `paper_title`; unique **per template** |
| label | string | shown on the single-certificate form and the Excel header |
| field_type | string/enum | `text`, `long_text`, `number`, `date`, `dropdown`, `certificate_number`, `qr_code` — see `App\Enums\TemplateFieldType` |
| is_required | boolean, default true | not applicable to `certificate_number`/`qr_code` (system-managed) |
| options | JSON, nullable | dropdown choices, e.g. `["Session Chair","Invited Speaker"]` |
| x_pt / y_pt | decimal | top-left position in PDF points (converted from browser canvas px at save time — see `docs/TEMPLATE_EDITOR.md`) |
| width_pt / height_pt | decimal | field box size in PDF points |
| font_family | string, default from an approved list | server-side embedded fonts only (see TEMPLATE_EDITOR.md §fonts) |
| font_size | decimal | in points |
| font_weight | string: `normal` \| `bold` | |
| text_align | string: `left` \| `center` \| `right` | |
| text_color | string | hex, e.g. `#000000` |
| show_on_verification | boolean, default true | drives what the public `/verify/{codeword}` page displays |
| verification_label | string, nullable | overrides `label` on the verification page when set |
| sort_order | integer | display order in the generated form / Excel columns |
| created_at / updated_at | timestamps | |

Unique constraint: (`template_id`, `field_key`).

Every one of: the single-certificate form, the Excel template's header row + column validation,
the PDF field-writing loop, and the verification-page renderer reads this table and only this
table to know what fields exist for a given certificate. See `TemplateFieldSchemaService` in
`docs/ARCHITECTURE.md` §2.

## `certificate_batches`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| template_id | FK -> certificate_templates.id | |
| name | string, nullable | admin-supplied label, e.g. "BECITHCON Session Chairs — Day 1" |
| status | string/enum: `pending`, `processing`, `completed`, `partial`, `failed` | |
| total_rows | integer | |
| success_count | integer, default 0 | |
| failed_count | integer, default 0 | |
| import_disk / import_path | string | uploaded Excel file |
| error_report_path | string, nullable | generated validation-error report |
| zip_disk / zip_path | string, nullable | generated download archive |
| zip_expires_at | timestamp, nullable | drives scheduled cleanup |
| created_by | FK -> users.id | |
| created_at / updated_at | timestamps | |

## `certificates`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| template_id | FK -> certificate_templates.id | |
| batch_id | FK -> certificate_batches.id, nullable | null for single-generated certificates |
| certificate_number | string, **unique** | human-readable |
| codeword | string, **unique**, indexed | verification secret |
| recipient_name | string, nullable | denormalized copy of whichever field represents the recipient, purely for admin search/listing convenience — the `data` JSON is still authoritative |
| data | JSON | `field_key => value` for every `template_fields` row on this template at generation time |
| status | string/enum: `active`, `revoked`, `reissued` | see `App\Enums\CertificateStatus` |
| issued_at | timestamp | |
| revoked_at | timestamp, nullable | |
| revocation_reason | text, nullable | |
| reissued_from_id | FK -> certificates.id, nullable, self-referencing | set on the **new** record when it supersedes an older one |
| pdf_disk / pdf_path | string | generated certificate PDF location |
| created_by | FK -> users.id | |
| created_at / updated_at | timestamps | |

Indexes: unique(`codeword`), unique(`certificate_number`), index(`template_id`),
index(`batch_id`), index(`status`), index(`created_at`).

`data` is a JSON snapshot taken at generation time — if `template_fields` changes later (a label
edited, a field added to the template), previously generated certificates are unaffected; they
keep rendering with the field definitions/labels that were current when *that certificate* was
made where relevant, and fall back gracefully for new fields.

Reissue mechanics: original row's `status` becomes `reissued` (not deleted); a new row is inserted
with a fresh `certificate_number`/`codeword` and `reissued_from_id` pointing at the original.
Both rows remain queryable forever.

## `verification_logs`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| certificate_id | FK -> certificates.id, nullable | null when the attempted codeword didn't match anything |
| codeword_attempted | string | logged even on miss, to spot abuse patterns |
| result | string/enum: `valid`, `revoked`, `invalid` | |
| created_at | timestamp only (no updated_at needed) | |

Deliberately minimal — no IP address, no user agent, no device fingerprinting stored. If abuse
monitoring later needs more, add a separate opt-in mechanism rather than expanding this table by
default (privacy-conscious per `docs/PROJECT_REQUIREMENTS.md`).

## `audit_logs`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK -> users.id, nullable | nullable for system/cron-initiated actions |
| action | string | e.g. `certificate.created`, `certificate.revoked`, `template.created`, `batch.generated`, `user.created` |
| subject_type | string | e.g. `Certificate`, `CertificateTemplate` |
| subject_id | bigint, nullable | |
| metadata | JSON, nullable | small, relevant context only — never passwords/secrets |
| created_at | timestamp only | |

## Design decisions worth recording

- **Not over-normalized on purpose**: `data` as JSON on `certificates` avoids an EAV table
  explosion (`certificate_values` with one row per field per certificate) while still being fully
  dynamic. `template_fields` is the normalized, queryable schema; `certificates.data` is the
  per-record payload validated against it at write time.
- **No separate `events` table for v1**: the public site's event content (BECITHCON, HTA
  exhibition) stays in `config/site.php`-equivalent static config per `docs/ARCHITECTURE.md`,
  since it isn't part of the certificate domain and the existing site already treats it as
  config, not database rows. Revisit only if the site needs an admin-editable events CMS later.
- **`recipient_name` is denormalized deliberately**: template fields are dynamic, so there's no
  guaranteed "name" column to query/sort/search by otherwise. It's populated from whichever field
  the template marks as the recipient-name field (a small `is_recipient_name` flag can be added to
  `template_fields` in Phase 3 if useful) purely for admin-list usability — `data` JSON stays the
  source of truth.
