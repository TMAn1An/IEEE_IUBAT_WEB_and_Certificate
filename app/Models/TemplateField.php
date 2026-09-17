<?php

namespace App\Models;

use App\Enums\TemplateFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateField extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_template_id',
        'label',
        'field_key',
        'field_type',
        'is_required',
        'show_on_verification',
        'verification_label',
        'options',
        'position',
        'style',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'field_type' => TemplateFieldType::class,
            'is_required' => 'boolean',
            'show_on_verification' => 'boolean',
            'options' => 'array',
            'position' => 'array',
            'style' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<CertificateTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }
}
