<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An automatically created Event Type + Event Name + Role combination — see
 * the qr_groups migration's docblock and docs/CERTIFICATE_SYSTEM.md §Simple
 * QR tool: automatic grouping. Never created directly by admin action alone;
 * always via QrGroupService::resolve()'s find-or-create.
 */
class QrGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'event_name',
        'role',
        'group_key',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<QrCertificate, $this> */
    public function certificates(): HasMany
    {
        return $this->hasMany(QrCertificate::class);
    }
}
