<?php

namespace App\Services\QrTool;

use App\Models\QrCategory;
use App\Models\QrCategoryField;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors App\Services\Templates\TemplateFieldService's rules (field-key
 * format, "exactly one recipient-name field" exclusivity, reordering) for
 * the simple QR tool's own field model. Kept as its own class rather than
 * shared/generalized, per the isolation requirement.
 */
class QrCategoryFieldService
{
    public static function isValidFieldKey(string $key): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]*$/', $key);
    }

    /** @param  array<string, mixed>  $data */
    public function create(QrCategory $category, array $data): QrCategoryField
    {
        return DB::transaction(function () use ($category, $data) {
            if (! empty($data['is_recipient_name'])) {
                $this->clearExistingRecipientField($category);
            }

            $nextSortOrder = (int) $category->fields()->max('sort_order') + 1;

            return $category->fields()->create([
                ...$data,
                'sort_order' => $data['sort_order'] ?? $nextSortOrder,
            ]);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(QrCategoryField $field, array $data): QrCategoryField
    {
        return DB::transaction(function () use ($field, $data) {
            if (! empty($data['is_recipient_name'])) {
                $this->clearExistingRecipientField($field->category, except: $field->id);
            }

            $field->update($data);

            return $field->fresh();
        });
    }

    public function delete(QrCategoryField $field): void
    {
        $field->delete();
    }

    public function moveUp(QrCategoryField $field): void
    {
        $this->swapWithNeighbor($field, direction: -1);
    }

    public function moveDown(QrCategoryField $field): void
    {
        $this->swapWithNeighbor($field, direction: 1);
    }

    private function swapWithNeighbor(QrCategoryField $field, int $direction): void
    {
        DB::transaction(function () use ($field, $direction) {
            $neighbor = QrCategoryField::query()
                ->where('qr_category_id', $field->qr_category_id)
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

    private function clearExistingRecipientField(QrCategory $category, ?int $except = null): void
    {
        $category->fields()
            ->where('is_recipient_name', true)
            ->when($except, fn ($query) => $query->where('id', '!=', $except))
            ->update(['is_recipient_name' => false]);
    }

    /**
     * Persists what the old tool's "Add role option" button kept only in
     * `localStorage` — see docs/CERTIFICATE_SYSTEM.md §Simple QR tool:
     * old-tool-parity rebuild. Case-insensitive duplicate check, matching
     * the old tool's own JS (`options.some(o => o.toLowerCase() ===
     * value.toLowerCase())`).
     *
     * @return list<string> the field's updated option list
     */
    public function addOption(QrCategoryField $field, string $value): array
    {
        $value = trim($value);
        $options = collect($field->options ?? []);

        if ($value !== '' && ! $options->contains(fn ($o) => strcasecmp($o, $value) === 0)) {
            $options->push($value);
            $field->update(['options' => $options->values()->all()]);
        }

        return $field->options ?? [];
    }

    /**
     * Removes a role option for FUTURE generation only — never touches an
     * already-created `qr_certificates` row, which stores the role as a
     * plain string value inside its own `data` JSON with no foreign key
     * back to this option list at all. Matches the old tool's own
     * behavior exactly (its options list was never anything more than a
     * dropdown's choices to begin with).
     *
     * @return list<string> the field's updated option list
     */
    public function removeOption(QrCategoryField $field, string $value): array
    {
        $options = collect($field->options ?? [])
            ->reject(fn ($o) => strcasecmp($o, $value) === 0)
            ->values()
            ->all();

        $field->update(['options' => $options]);

        return $options;
    }
}
