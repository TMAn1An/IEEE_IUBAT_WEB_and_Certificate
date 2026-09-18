<?php

namespace App\Services\Certificates\Import;

/**
 * The "special" mapping targets an Excel column can be assigned to, beyond
 * a template field's own `field_key`. See docs/CERTIFICATE_SYSTEM.md
 * §Excel column mapping.
 *
 * Deliberately no separate "Recipient Name" pseudo-target: the recipient
 * field is just a normal assignable template field (whichever one has
 * `is_recipient_name = true`), mapped to its own `field_key` like any other
 * field — exactly the same pattern the live QR-generation form already
 * uses (`fields[{field_key}]`, then the service copies that field's value
 * into `certificates.recipient_name`). An earlier version of this importer
 * used a separate `_recipient_name` target, which meant a field flagged
 * `is_recipient_name` could never satisfy its own "this required field
 * must be mapped" check — caught in manual testing before this shipped.
 */
final class ImportMappingTarget
{
    public const CODEWORD = '_codeword';

    public const CERTIFICATE_NUMBER = '_certificate_number';

    public const IGNORE = '_ignore';
}
