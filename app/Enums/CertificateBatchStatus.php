<?php

namespace App\Enums;

/**
 * See docs/CERTIFICATE_SYSTEM.md §Bulk generation for how a batch moves
 * through these — that behavior isn't built yet (Phase 7); this enum just
 * gives the `certificate_batches.status` column a real, documented type.
 */
enum CertificateBatchStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Partial => 'Partial',
            self::Failed => 'Failed',
        };
    }
}
