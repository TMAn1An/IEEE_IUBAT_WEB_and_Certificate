<?php

namespace App\Services\Certificates;

use App\Models\CertificateTemplate;
use App\Services\Certificates\Pdf\PdfPageBox;

/**
 * Builds the two immutability snapshots stored on `certificates` at
 * issuance time. See docs/CERTIFICATE_SYSTEM.md §Snapshot strategy.
 *
 * Split into two arrays rather than one blob because they answer different
 * questions and are consumed by different code: `template_snapshot` is
 * "what field definitions existed" (what the certificate detail page reads
 * to label stored values); `layout_snapshot` is "exactly where everything
 * was drawn" (what a future re-render/audit feature would read). Later
 * edits to the live template/fields (label changes, position drags, a
 * replaced background) never touch these — they're a copy, not a
 * reference. See §Template modification rules for why editing the live
 * template is still allowed once certificates exist.
 */
class CertificateSnapshotService
{
    /** @return array<string, mixed> */
    public function templateSnapshot(CertificateTemplate $template): array
    {
        return [
            'template_id' => $template->id,
            'name' => $template->name,
            'slug' => $template->slug,
            'fields' => $template->fields->map(fn ($field) => [
                'id' => $field->id,
                'label' => $field->label,
                'field_key' => $field->field_key,
                'field_type' => $field->field_type->value,
                'is_required' => $field->is_required,
                'is_recipient_name' => $field->is_recipient_name,
                'options' => $field->options,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function layoutSnapshot(CertificateTemplate $template, PdfPageBox $pageBox): array
    {
        return [
            'page_width' => $pageBox->width,
            'page_height' => $pageBox->height,
            'media_box_llx' => $pageBox->llx,
            'media_box_lly' => $pageBox->lly,
            'fields' => $template->fields->mapWithKeys(fn ($field) => [
                $field->field_key => [
                    'position' => $field->position,
                    'style' => $field->style,
                ],
            ])->all(),
            'certificate_number' => $template->certificate_number_layout,
            'qr_code' => $template->qr_code_layout,
        ];
    }
}
