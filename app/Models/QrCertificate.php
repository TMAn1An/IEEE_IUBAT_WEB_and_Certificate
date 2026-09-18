<?php

namespace App\Models;

use App\Enums\QrCertificateStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'qr_category_id',
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
