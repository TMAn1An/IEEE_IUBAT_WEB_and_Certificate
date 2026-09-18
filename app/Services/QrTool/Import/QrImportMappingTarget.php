<?php

namespace App\Services\QrTool\Import;

/**
 * The "special" mapping targets an Excel column can be assigned to, beyond
 * a category field's own `key`. Matches the actual old tool's Excel
 * columns (SL, Conference, Role, Name, Session, Codeword, Created At, QR
 * File -- inspected directly from IEEEQRCODEGENERATOR-main/app.py's
 * HEADERS/append_registration()): SL and QR File are always ignored
 * (SL was a per-file row counter with no meaning outside that file; QR File
 * was a local filesystem path -- the QR is regenerated from the preserved
 * codeword instead, never imported). Name maps to whichever field is
 * flagged `is_recipient_name`, same as the live generate-QR form.
 *
 * Every import now happens INTO a destination QrGroup (see
 * QrCategoryImportValidator/QrCategoryImportService), which is authoritative
 * for Event Type/Event Name/Role -- so, unlike the old per-category
 * importer, a file's own Conference/Role columns are no longer mapped as
 * value overrides. They can only be used to double check the file actually
 * belongs to the selected group (CONFERENCE_VALIDATE/ROLE_VALIDATE) — see
 * docs/CERTIFICATE_SYSTEM.md §Simple QR tool: group-based import.
 */
final class QrImportMappingTarget
{
    public const CODEWORD = '_codeword';

    /** The old tool's "Created At" column -- preserves the original registration date/time when present and parseable. */
    public const CREATED_AT = '_created_at';

    /** The old tool's "Conference" column, validate-only: must match the destination group's event_name if present. */
    public const CONFERENCE_VALIDATE = '_conference_validate';

    /** The old tool's "Role" column, validate-only: must match the destination group's role if present. */
    public const ROLE_VALIDATE = '_role_validate';

    public const IGNORE = '_ignore';
}
