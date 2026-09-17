<?php

namespace App\Services\Templates;

use App\Models\CertificateTemplate;
use App\Models\TemplateField;
use Illuminate\Support\Facades\DB;

/**
 * Field-level rules that don't belong in a controller: field-key format,
 * the "exactly one recipient-name field" exclusivity rule, and manual
 * reordering. See docs/CERTIFICATE_SYSTEM.md §Dynamic fields.
 */
class TemplateFieldService
{
    /**
     * Field keys reserved for the system-managed layout elements (see
     * App\Enums\TemplateFieldType docblock) — an ordinary field can't use
     * one of these even though its own field_type will never be
     * certificate_number/qr_code, because a later phase looks these keys
     * up by convention when placing system elements on the PDF.
     */
    private const RESERVED_FIELD_KEYS = ['certificate_number', 'qr_code'];

    public static function isValidFieldKey(string $key): bool
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            return false;
        }

        return ! in_array($key, self::RESERVED_FIELD_KEYS, true);
    }

    /** @param  array<string, mixed>  $data */
    public function create(CertificateTemplate $template, array $data): TemplateField
    {
        return DB::transaction(function () use ($template, $data) {
            if (! empty($data['is_recipient_name'])) {
                $this->clearExistingRecipientField($template);
            }

            $nextSortOrder = (int) $template->fields()->max('sort_order') + 1;

            return $template->fields()->create([
                ...$data,
                'sort_order' => $data['sort_order'] ?? $nextSortOrder,
            ]);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(TemplateField $field, array $data): TemplateField
    {
        return DB::transaction(function () use ($field, $data) {
            if (! empty($data['is_recipient_name'])) {
                $this->clearExistingRecipientField($field->template, except: $field->id);
            }

            // field_key is intentionally still editable in Phase 3 — no
            // certificate has ever been generated against a template yet
            // (certificate generation doesn't exist until Phase 5), so
            // there is nothing whose `data` JSON could go stale. Once
            // generation exists, add a guard here such as:
            //   if ($field->template->certificates()->exists()) { unset($data['field_key']); }
            // rather than opening this method up again from scratch.
            $field->update($data);

            return $field->fresh();
        });
    }

    /**
     * Fields are hard-deletable in Phase 3 for the same "nothing references
     * them yet" reason as field_key above. Once certificates can exist
     * against a template, guard this the same way:
     *   if ($field->template->certificates()->exists()) { throw ...; }
     * Deleting a field never touches `certificates` rows — there's no
     * cascade from template_fields to certificates in the schema at all
     * (see docs/DATABASE_DESIGN.md), so this can't silently destroy
     * certificate data even by accident.
     */
    public function delete(TemplateField $field): void
    {
        $field->delete();
    }

    public function moveUp(TemplateField $field): void
    {
        $this->swapWithNeighbor($field, direction: -1);
    }

    public function moveDown(TemplateField $field): void
    {
        $this->swapWithNeighbor($field, direction: 1);
    }

    private function swapWithNeighbor(TemplateField $field, int $direction): void
    {
        DB::transaction(function () use ($field, $direction) {
            $neighbor = TemplateField::query()
                ->where('certificate_template_id', $field->certificate_template_id)
                ->where('sort_order', $direction < 0 ? '<' : '>', $field->sort_order)
                ->orderBy('sort_order', $direction < 0 ? 'desc' : 'asc')
                ->first();

            if (! $neighbor) {
                return;
            }

            $fieldOrder = $field->sort_order;
            $neighborOrder = $neighbor->sort_order;

            $field->update(['sort_order' => $neighborOrder]);
            $neighbor->update(['sort_order' => $fieldOrder]);
        });
    }

    private function clearExistingRecipientField(CertificateTemplate $template, ?int $except = null): void
    {
        $template->fields()
            ->where('is_recipient_name', true)
            ->when($except, fn ($query) => $query->where('id', '!=', $except))
            ->update(['is_recipient_name' => false]);
    }
}
