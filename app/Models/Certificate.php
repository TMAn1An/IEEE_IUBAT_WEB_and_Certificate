<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** SoftDeletes — see App\Models\QrCertificate's identical docblock and docs/CERTIFICATE_SYSTEM.md §Controlled deletion. */
class Certificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'certificate_template_id',
        'certificate_batch_id',
        'certificate_number',
        'codeword',
        'recipient_name',
        'data',
        'pdf_path',
        'template_snapshot',
        'layout_snapshot',
        'status',
        'issued_at',
        'revoked_at',
        'revocation_reason',
        'reissued_from_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'template_snapshot' => 'array',
            'layout_snapshot' => 'array',
            'status' => CertificateStatus::class,
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CertificateTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    /** @return BelongsTo<CertificateBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CertificateBatch::class, 'certificate_batch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The certificate this one superseded, if it was created via reissue. */
    /** @return BelongsTo<Certificate, $this> */
    public function reissuedFrom(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'reissued_from_id');
    }

    /** The certificate that superseded this one, if it was reissued. */
    /** @return HasOne<Certificate, $this> */
    public function reissuedTo(): HasOne
    {
        return $this->hasOne(Certificate::class, 'reissued_from_id');
    }
}
