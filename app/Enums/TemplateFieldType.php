<?php

namespace App\Enums;

/**
 * See docs/TEMPLATE_EDITOR.md §Field types (v1) and docs/CERTIFICATE_SYSTEM.md
 * §System fields vs input fields.
 *
 * `CertificateNumber` and `QrCode` are NOT admin-assignable input fields —
 * they're system-managed layout elements (a certificate number string and a
 * QR image the system places on the PDF itself), never participant-entered
 * data, never an Excel column. Phase 3's admin UI only ever offers
 * `assignable()`; the other two cases exist now purely so the column/enum
 * type is ready for Phase 4, when the template editor gains PDF placement
 * for them. See CLAUDE.md's "System fields" note and
 * docs/CERTIFICATE_SYSTEM.md for the full distinction.
 */
enum TemplateFieldType: string
{
    case Text = 'text';
    case LongText = 'long_text';
    case Number = 'number';
    case Date = 'date';
    case Dropdown = 'dropdown';
    case CertificateNumber = 'certificate_number';
    case QrCode = 'qr_code';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::LongText => 'Long text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Dropdown => 'Dropdown',
            self::CertificateNumber => 'Certificate number',
            self::QrCode => 'QR code',
        };
    }

    /** True for the ordinary participant-data types an admin may assign to a field in Phase 3. */
    public function isAssignable(): bool
    {
        return ! in_array($this, [self::CertificateNumber, self::QrCode], true);
    }

    /** @return list<self> The types offered in the "Add field" / "Edit field" form. */
    public static function assignable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->isAssignable()));
    }
}
