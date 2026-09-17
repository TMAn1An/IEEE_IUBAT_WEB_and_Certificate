<?php

namespace App\Services\Templates;

use App\Enums\CertificateTemplateStatus;
use App\Enums\TemplateFieldType;
use App\Models\CertificateTemplate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Template-level rules that don't belong in a controller: slug generation
 * and the draft -> active activation gate. See docs/CERTIFICATE_SYSTEM.md
 * §Template activation validation for the rule list this enforces.
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

    /**
     * Everything wrong with activating $template right now, empty when it's
     * ready. Deliberately does not check for an uploaded PDF or PDF
     * placement — that's a Phase 4 "generation ready" concept layered on
     * top of, not merged into, this draft->active gate (see
     * docs/CERTIFICATE_SYSTEM.md).
     *
     * @return list<string>
     */
    public function activationErrors(CertificateTemplate $template): array
    {
        $errors = [];
        $fields = $template->fields()->get();

        if ($fields->isEmpty()) {
            $errors[] = 'The template needs at least one field before it can be activated.';

            return $errors;
        }

        $recipientFields = $fields->where('is_recipient_name', true);
        if ($recipientFields->count() === 0) {
            $errors[] = 'Exactly one field must be designated as the recipient name field.';
        } elseif ($recipientFields->count() > 1) {
            // Should be unreachable — TemplateFieldService enforces exclusivity on write —
            // but checked here too since activation is the last real gate before this
            // template can be used, and defense-in-depth is cheap.
            $errors[] = 'More than one field is marked as the recipient name field.';
        }

        $keys = $fields->pluck('field_key');
        if ($keys->count() !== $keys->unique()->count()) {
            // Unreachable in practice — the DB has a unique(template_id, field_key)
            // constraint — kept as an explicit, readable check per the brief.
            $errors[] = 'Two or more fields share the same field key.';
        }

        foreach ($fields as $field) {
            if (! TemplateFieldService::isValidFieldKey($field->field_key)) {
                $errors[] = "Field \"{$field->label}\" has an invalid field key ({$field->field_key}).";
            }

            if ($field->field_type === TemplateFieldType::Dropdown) {
                $options = collect($field->options ?? [])->filter(fn ($option) => trim((string) $option) !== '');
                if ($options->isEmpty()) {
                    $errors[] = "Dropdown field \"{$field->label}\" needs at least one option.";
                }
            }
        }

        return $errors;
    }

    /** @throws ValidationException when the template isn't ready to activate. */
    public function activate(CertificateTemplate $template): void
    {
        $errors = $this->activationErrors($template);

        if ($errors !== []) {
            throw ValidationException::withMessages(['activation' => $errors]);
        }

        $template->update(['status' => CertificateTemplateStatus::Active]);
    }

    public function archive(CertificateTemplate $template): void
    {
        $template->update(['status' => CertificateTemplateStatus::Archived]);
    }
}
