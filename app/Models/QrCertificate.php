<?php

namespace App\Models;

use App\Enums\QrCertificateStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SoftDeletes (added for controlled deletion — see
 * docs/CERTIFICATE_SYSTEM.md §Controlled deletion): the default query
 * builder — every list/records/verification lookup in this codebase —
 * automatically excludes a trashed row via Eloquent's own global scope.
 * The ONLY code path that ever sets `deleted_at` is
 * App\Services\Deletion\DeletionRequestService::approve().
 */
class QrCertificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'qr_category_id',
        'qr_group_id',
        'recipient_name',
        'event_name',
        'data',
        'codeword',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'status' => QrCertificateStatus::class,
        ];
    }

    /** @return BelongsTo<QrCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(QrCategory::class, 'qr_category_id');
    }

    /** @return BelongsTo<QrGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(QrGroup::class, 'qr_group_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
