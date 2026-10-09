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
        'original_filename',
        'file_mime',
        'file_size',
        'page_width',
        'page_height',
        'certificate_number_layout',
        'qr_code_layout',
        'status',
        'created_by',
        'editor_project_path',
        'editor_schema',
        'editor_schema_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => CertificateTemplateStatus::class,
            'page_width' => 'decimal:2',
            'page_height' => 'decimal:2',
            'file_size' => 'integer',
            'certificate_number_layout' => 'array',
            'qr_code_layout' => 'array',
            'editor_schema' => 'array',
            'editor_schema_version' => 'integer',
        ];
    }

    /** Whether a certificate background has been uploaded yet. */
    public function hasBackground(): bool
    {
        return $this->source_pdf_path !== null;
    }

    /** Whether an admin has saved a PDF Studio project (bundle) for this template. */
    public function hasEditorProject(): bool
    {
        return $this->editor_project_path !== null;
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
