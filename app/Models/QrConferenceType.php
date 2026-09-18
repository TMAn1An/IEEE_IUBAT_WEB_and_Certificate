<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QrConferenceType extends Model
{
    protected $fillable = ['name'];

    /** @return HasMany<QrConferenceOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QrConferenceOption::class)->orderBy('name');
    }
}
