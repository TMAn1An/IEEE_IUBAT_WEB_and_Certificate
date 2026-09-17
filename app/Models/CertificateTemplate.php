<?php

namespace App\Models;

use App\Enums\CertificateTemplateStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'source_pdf_path',
        'page_width',
        'page_height',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CertificateTemplateStatus::class,
            'page_width' => 'decimal:2',
            'page_height' => 'decimal:2',
        ];
    }

    /** @return HasMany<TemplateField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(TemplateField::class)->orderBy('sort_order');
    }

    /** @return HasMany<Certificate, $this> */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /** @return HasMany<CertificateBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(CertificateBatch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
