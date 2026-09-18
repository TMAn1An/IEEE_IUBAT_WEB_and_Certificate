<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrConferenceOption extends Model
{
    protected $fillable = ['qr_conference_type_id', 'name'];

    /** @return BelongsTo<QrConferenceType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(QrConferenceType::class, 'qr_conference_type_id');
    }
}
