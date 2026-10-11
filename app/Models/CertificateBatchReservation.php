<?php

namespace App\Models;

use App\Enums\CertificateBatchReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateBatchReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_batch_id',
        'row_index',
        'certificate_number',
        'codeword',
        'recipient_name',
        'data',
        'qr_filename',
        'photo_path',
        'status',
        'certificate_id',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'row_index' => 'integer',
            'data' => 'array',
            'status' => CertificateBatchReservationStatus::class,
        ];
    }

    /** @return BelongsTo<CertificateBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CertificateBatch::class, 'certificate_batch_id');
    }

    /** @return BelongsTo<Certificate, $this> */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }
}
