<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A snapshot, not a pointer: key, label, type, raw value and a
 * human-readable display value are all frozen at submission time, so the
 * row stays meaningful no matter how the live field changes later.
 * `value` is JSON-encoded so a checkbox group's list survives intact.
 */
class FormSubmissionValue extends Model
{
    /** Values are written once; there is no `updated_at`. */
    const UPDATED_AT = null;

    protected $fillable = [
        'form_submission_id',
        'form_field_id',
        'field_key',
        'field_label_snapshot',
        'field_type_snapshot',
        'value',
        'display_value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /** @return BelongsTo<FormSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    /** @return BelongsTo<FormField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }
}
