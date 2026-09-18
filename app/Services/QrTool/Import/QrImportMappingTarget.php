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
 */
final class QrImportMappingTarget
{
    public const CODEWORD = '_codeword';

    /** The old tool's "Conference" column -- an optional per-row override of the category's own `event_name`. */
    public const EVENT_NAME = '_event_name';

    /** The old tool's "Created At" column -- preserves the original registration date/time when present and parseable. */
    public const CREATED_AT = '_created_at';

    public const IGNORE = '_ignore';
}
