<?php

namespace App\Services\Templates;

use App\Enums\CertificateTemplateStatus;
use App\Models\CertificateTemplate;
use Illuminate\Support\Str;

/**
 * Template-level rules that don't belong in a controller: slug generation
 * and archiving. PDF Certificates (docs/PDF_STUDIO_INTEGRATION.md) has no
 * draft->active activation gate — a template becomes usable for generation
 * as soon as it has a saved PDF Studio project (`editor_schema !== null`),
 * checked directly by DirectBatchService::prepare(), not by a `status`
 * field. The old draft/active/archived activation gate (requiring
 * `template_fields` rows from the removed manual designer) was found to
 * block the PDF Studio workflow for no reason and was removed in the admin
 * workflow cleanup — see docs/CHANGELOG.md.
 */
class TemplateService
{
    /** A unique, URL-safe slug derived from $name, appending -2, -3, ... on collision. */
    public function generateUniqueSlug(string $name, ?int $ignoreTemplateId = null): string
    {
        $base = Str::slug($name) ?: 'template';
        $slug = $base;
        $suffix = 2;

        while (
            CertificateTemplate::query()
                ->where('slug', $slug)
                ->when($ignoreTemplateId, fn ($query) => $query->where('id', '!=', $ignoreTemplateId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /** Hides $template from the "Saved templates" list. Does not delete it or its batches/certificates. */
    public function archive(CertificateTemplate $template): void
    {
        $template->update(['status' => CertificateTemplateStatus::Archived]);
    }
}
