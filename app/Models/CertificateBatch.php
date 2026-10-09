<?php

namespace App\Models;

use App\Enums\CertificateBatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_template_id',
        'source',
        'name',
        'status',
        'total_rows',
        'successful_rows',
        'failed_rows',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CertificateBatchStatus::class,
            'total_rows' => 'integer',
            'successful_rows' => 'integer',
            'failed_rows' => 'integer',
        ];
    }

    /** @return BelongsTo<CertificateTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    /** @return HasMany<Certificate, $this> */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * PDF Editor Bridge reservations — only populated for batches created
     * via that path (`source === 'pdf_editor_bridge'`). See
     * docs/CERTIFICATE_SYSTEM.md §PDF Editor Bridge.
     *
     * @return HasMany<CertificateBatchReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(CertificateBatchReservation::class)->orderBy('row_index');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
