<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QrCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'event_name',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<QrCategoryField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(QrCategoryField::class)->orderBy('sort_order');
    }

    /** @return HasMany<QrCertificate, $this> */
    public function certificates(): HasMany
    {
        return $this->hasMany(QrCertificate::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
