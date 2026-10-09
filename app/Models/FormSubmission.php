<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Created only by App\Services\Forms\FormSubmissionService. No route
 * updates or deletes a submission. See docs/FORM_BUILDER.md §Submission
 * architecture.
 */
class FormSubmission extends Model
{
    protected $fillable = [
        'form_id',
        'submitted_by',
        'submitted_at',
        'form_version',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'form_version' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return HasMany<FormSubmissionValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(FormSubmissionValue::class)->orderBy('id');
    }
}
