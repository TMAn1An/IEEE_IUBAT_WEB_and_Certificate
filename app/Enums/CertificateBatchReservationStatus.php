<?php

namespace App\Enums;

/**
 * See docs/CERTIFICATE_SYSTEM.md §PDF Editor Bridge. `Reserved` means a
 * certificate_number/codeword/QR exist but no PDF or `certificates` row
 * yet. `Finalized` means the editor's generated PDF was matched and the
 * real `certificates` row was created. `Failed` means the uploaded PDF for
 * this row was missing, unreadable, or rejected.
 */
enum CertificateBatchReservationStatus: string
{
    case Reserved = 'reserved';
    case Finalized = 'finalized';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Reserved => 'Reserved',
            self::Finalized => 'Finalized',
            self::Failed => 'Failed',
        };
    }
}
