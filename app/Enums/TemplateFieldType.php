<?php

namespace App\Enums;

/**
 * See docs/TEMPLATE_EDITOR.md §Field types (v1). The template editor UI
 * itself isn't built yet (Phase 4); this enum just gives the
 * `template_fields.field_type` column a real, documented type.
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
}
