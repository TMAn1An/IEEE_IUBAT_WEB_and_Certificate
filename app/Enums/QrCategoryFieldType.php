<?php

namespace App\Enums;

/**
 * Deliberately smaller than TemplateFieldType -- no CertificateNumber/QrCode
 * system-field cases, since the simple QR tool has no PDF/layout concept
 * for those to describe. Every case here is a normal, admin-assignable
 * input field.
 */
enum QrCategoryFieldType: string
{
    case Text = 'text';
    case LongText = 'long_text';
    case Number = 'number';
    case Date = 'date';
    case Dropdown = 'dropdown';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::LongText => 'Long text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Dropdown => 'Dropdown',
        };
    }
}
