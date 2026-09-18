<?php

namespace App\Services\Templates;

use App\Models\CertificateTemplate;
use App\Models\TemplateField;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Persists a designer "Save Layout" submission: the page dimensions PDF.js
 * reported for the current background, every dynamic field's position/
 * style, and the two system elements' layout. See
 * docs/CERTIFICATE_SYSTEM.md §Coordinate system for what the numbers mean
 * (PDF points, bottom-left origin) — this service just writes what
 * `SaveTemplateLayoutRequest` already validated; it re-checks field
 * ownership as a second IDOR guard rather than trusting the Form Request
 * alone.
 */
class TemplateLayoutService
{
    /** @param  array<string, mixed>  $data  Already validated by SaveTemplateLayoutRequest. */
    public function saveLayout(CertificateTemplate $template, array $data): void
    {
        DB::transaction(function () use ($template, $data) {
            $template->update([
                'page_width' => $data['page_width'],
                'page_height' => $data['page_height'],
                'certificate_number_layout' => $data['certificate_number'] ?? null,
                'qr_code_layout' => $this->squareQrLayout($data['qr_code'] ?? null),
            ]);

            $fieldIds = collect($data['fields'] ?? [])->pluck('id');
            $ownedFieldIds = TemplateField::query()
                ->where('certificate_template_id', $template->id)
                ->whereIn('id', $fieldIds)
                ->pluck('id');

            if ($ownedFieldIds->count() !== $fieldIds->unique()->count()) {
                // A submitted field id didn't belong to this template — same IDOR
                // guard TemplateFieldController uses, just at the batch-save level.
                throw ValidationException::withMessages([
                    'fields' => 'One or more fields do not belong to this template.',
                ]);
            }

            foreach ($data['fields'] ?? [] as $fieldData) {
                TemplateField::where('id', $fieldData['id'])->update([
                    'position' => $fieldData['position'],
                    'style' => $fieldData['style'] ?? null,
                ]);
            }
        });
    }

    /**
     * QR is square by default (per the brief) — the server is the final
     * authority on that, not client-side dragging, so height always mirrors
     * width regardless of what was submitted.
     *
     * @param  array<string, mixed>|null  $layout
     * @return array<string, mixed>|null
     */
    private function squareQrLayout(?array $layout): ?array
    {
        if ($layout === null) {
            return null;
        }

        $layout['height'] = $layout['width'];

        return $layout;
    }
}
