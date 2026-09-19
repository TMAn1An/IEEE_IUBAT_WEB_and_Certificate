<?php

namespace App\Enums;

use App\Models\Certificate;
use App\Models\QrCertificate;
use Illuminate\Database\Eloquent\Model;

/**
 * The ONLY safe way a deletion request/audit log row identifies which
 * table `record_id` points into. Never accept a raw model class name (or
 * this enum's own case name) as free-text client input -- the controller
 * routes are model-bound per record type (see
 * Admin\DeletionRequestController::requestQr()/requestCertificate()), so
 * this enum value is always set server-side, never read out of a request
 * body. See docs/CERTIFICATE_SYSTEM.md §Controlled deletion.
 */
enum DeletableRecordType: string
{
    case QrCertificate = 'qr_certificate';
    case Certificate = 'certificate';

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::QrCertificate => QrCertificate::class,
            self::Certificate => Certificate::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::QrCertificate => 'Simple QR record',
            self::Certificate => 'Advanced certificate',
        };
    }
}
