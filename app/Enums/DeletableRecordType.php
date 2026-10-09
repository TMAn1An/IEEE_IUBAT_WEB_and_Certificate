<?php

namespace App\Enums;

use App\Models\Certificate;
use App\Models\QrCertificate;
use Illuminate\Database\Eloquent\Model;
use TMAn1An\FormBuilder\Models\Form;
use TMAn1An\FormBuilder\Models\Page;

/**
 * The ONLY safe way a deletion request/audit log row identifies which
 * table `record_id` points into. Never accept a raw model class name (or
 * this enum's own case name) as free-text client input -- the controller
 * routes are model-bound per record type (see
 * Admin\DeletionRequestController::requestQr()/requestCertificate()), so
 * this enum value is always set server-side, never read out of a request
 * body. See docs/CERTIFICATE_SYSTEM.md §Controlled deletion.
 *
 * `Form` and `Page` are AUDIT-ONLY record types for the Form + Page Builder
 * package (tman1an/formbuilder, see docs/FORM_BUILDER.md §Audit
 * integration): they let builder actions land in the same append-only
 * Logbook instead of a second audit system, but they are never deletion
 * targets -- isDeletable() is false and DeletionRequestService::request()
 * refuses them.
 */
enum DeletableRecordType: string
{
    case QrCertificate = 'qr_certificate';
    case Certificate = 'certificate';
    case Form = 'form';
    case Page = 'page';

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::QrCertificate => QrCertificate::class,
            self::Certificate => Certificate::class,
            self::Form => Form::class,
            self::Page => Page::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::QrCertificate => 'Simple QR record',
            self::Certificate => 'Advanced certificate',
            self::Form => 'Form',
            self::Page => 'Page',
        };
    }

    public function isDeletable(): bool
    {
        return $this !== self::Form && $this !== self::Page;
    }
}
