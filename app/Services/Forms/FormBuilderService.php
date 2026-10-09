<?php

namespace App\Services\Forms;

use App\Enums\AuditEventType;
use App\Enums\FormFieldType;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\Forms\Style\FormStyleSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every write to a form's definition or lifecycle. Multi-row writes run in
 * one transaction with the form row locked, so a save either lands
 * completely or not at all -- the builder can never be left half-saved.
 * See docs/FORM_BUILDER.md §Save reliability.
 */
class FormBuilderService
{
    private const FIELD_ATTRIBUTES = ['label', 'key', 'type', 'required', 'is_active', 'sort_order', 'settings', 'style_settings', 'conditional_rules'];

    public function __construct(
        private readonly FormDefinitionValidator $definitions,
        private readonly FormHtmlSanitizer $html,
        private readonly FormAuditLogger $audit,
    ) {}

    public function create(string $name, ?string $description, User $actor): Form
    {
        return DB::transaction(function () use ($name, $description, $actor) {
            $form = Form::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'description' => $description ?: null,
                'status' => FormStatus::Draft,
                'settings' => FormSettingsSchema::normalize(null),
                'style_settings' => FormStyleSchema::defaults(),
                'lock_version' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->audit->log(AuditEventType::FormCreated, $form, $actor, "Created form \"{$form->name}\".");

            return $form;
        });
    }

    /**
     * Persists a normalized definition (FormDefinitionValidator::normalize()).
     *
     * @param  array<string, mixed>  $definition
     *
     * @throws FormVersionConflictException when $expectedVersion is stale
     */
    public function saveDefinition(Form $form, array $definition, int $expectedVersion, User $actor, bool $publish = false, bool $autosave = false): Form
    {
        return DB::transaction(function () use ($form, $definition, $expectedVersion, $actor, $publish, $autosave) {
            /** @var Form $locked */
            $locked = Form::query()->lockForUpdate()->findOrFail($form->id);

            if ($locked->lock_version !== $expectedVersion) {
                throw new FormVersionConflictException($locked->lock_version);
            }

            $existing = $locked->fields()->get()->keyBy('id');
            $withSubmissions = $this->definitions->fieldIdsWithSubmissions($locked);
            $payloadIds = collect($definition['fields'])->pluck('id')->filter()->all();
            $changes = ['added' => [], 'deleted' => [], 'archived' => []];

            // 1. Fields dropped from the builder: archive if anything was
            //    ever submitted for them, otherwise delete outright.
            foreach ($existing as $id => $field) {
                if (in_array($id, $payloadIds, true)) {
                    continue;
                }
                if (isset($withSubmissions[$id])) {
                    $field->update(['is_active' => false]);
                    $changes['archived'][] = $field->key;
                } else {
                    $field->delete();
                    $changes['deleted'][] = $field->key;
                }
            }

            // 2. Park renamed keys on a temporary value first, so swapping
            //    two keys can't trip the (form_id, key) unique index mid-save.
            foreach ($definition['fields'] as $field) {
                if ($field['id'] !== null && $existing[$field['id']]->key !== $field['key']) {
                    $existing[$field['id']]->update(['key' => '__tmp_'.$field['id']]);
                }
            }

            // 3. Upsert in builder order.
            foreach ($definition['fields'] as $field) {
                $attributes = Arr::only($field, self::FIELD_ATTRIBUTES);
                if ($field['id'] !== null) {
                    $existing[$field['id']]->update($attributes);
                } else {
                    $locked->fields()->create($attributes);
                    $changes['added'][] = $field['key'];
                }
            }

            // 4. The form row itself.
            $attributes = Arr::only($definition, ['name', 'slug', 'description', 'settings', 'style_settings']);
            if ($definition['custom'] !== null) {
                $attributes += $definition['custom'];
            }
            $attributes['lock_version'] = $locked->lock_version + 1;
            $attributes['updated_by'] = $actor->id;

            $wasActive = $locked->status === FormStatus::Active;
            if ($publish) {
                $attributes['status'] = FormStatus::Active;
                $attributes['published_at'] = $locked->published_at ?? now();
            }

            $locked->update($attributes);

            // Autosaves (drafts only) are not individually logged -- the
            // next explicit save/publish records the accumulated result.
            if (! $autosave) {
                $this->audit->log(AuditEventType::FormUpdated, $locked, $actor, "Saved form \"{$locked->name}\".", $changes + [
                    'field_count' => count($definition['fields']),
                ]);
            }
            if ($publish && ! $wasActive) {
                $this->audit->log(AuditEventType::FormPublished, $locked, $actor, "Published form \"{$locked->name}\".");
            }

            return $locked->fresh('fields');
        });
    }

    public function publish(Form $form, User $actor): Form
    {
        $hasInput = $form->activeFields()->get()->contains(fn (FormField $f) => $f->type->acceptsUserInput());
        if (! $hasInput) {
            throw ValidationException::withMessages(['status' => 'Add at least one input field before publishing.']);
        }

        return $this->transition($form, $actor, FormStatus::Active, AuditEventType::FormPublished, 'Published');
    }

    public function deactivate(Form $form, User $actor): Form
    {
        return $this->transition($form, $actor, FormStatus::Inactive, AuditEventType::FormDeactivated, 'Deactivated');
    }

    public function archive(Form $form, User $actor): Form
    {
        return $this->transition($form, $actor, FormStatus::Archived, AuditEventType::FormArchived, 'Archived');
    }

    /** Archived -> Deactivated (never straight back to live). */
    public function restore(Form $form, User $actor): Form
    {
        return $this->transition($form, $actor, FormStatus::Inactive, AuditEventType::FormRestored, 'Restored');
    }

    private function transition(Form $form, User $actor, FormStatus $to, AuditEventType $event, string $verb): Form
    {
        return DB::transaction(function () use ($form, $actor, $to, $event, $verb) {
            $locked = Form::query()->lockForUpdate()->findOrFail($form->id);
            if ($locked->status === $to) {
                return $locked;
            }

            $from = $locked->status;
            $locked->update([
                'status' => $to,
                'published_at' => $to === FormStatus::Active ? ($locked->published_at ?? now()) : $locked->published_at,
                'updated_by' => $actor->id,
            ]);

            $this->audit->log($event, $locked, $actor, "{$verb} form \"{$locked->name}\".", ['from' => $from->value, 'to' => $to->value]);

            return $locked;
        });
    }

    /**
     * Copies settings, design, custom code (HTML re-sanitized) and the
     * active fields -- never submissions. The copy starts as a draft.
     */
    public function duplicate(Form $source, User $actor): Form
    {
        return DB::transaction(function () use ($source, $actor) {
            $name = Str::limit('Copy of '.$source->name, 150, '');

            $copy = Form::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($source->slug.'-copy'),
                'description' => $source->description,
                'status' => FormStatus::Draft,
                'settings' => $source->settings,
                'style_settings' => $source->style_settings,
                'custom_css' => $source->custom_css,
                'custom_html_before' => $this->html->sanitize($source->custom_html_before) ?: null,
                'custom_html_after' => $this->html->sanitize($source->custom_html_after) ?: null,
                'custom_js' => $source->custom_js,
                'lock_version' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($source->activeFields()->get() as $index => $field) {
                $settings = $field->settings ?? [];
                if ($field->type === FormFieldType::Html && isset($settings['content'])) {
                    $settings['content'] = $this->html->sanitize($settings['content']);
                }

                $copy->fields()->create([
                    'label' => $field->label,
                    'key' => $field->key,
                    'type' => $field->type,
                    'required' => $field->required,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'settings' => $settings,
                    'style_settings' => $field->style_settings,
                    'conditional_rules' => $field->conditional_rules,
                ]);
            }

            $this->audit->log(AuditEventType::FormDuplicated, $copy, $actor, "Duplicated \"{$source->name}\" as \"{$copy->name}\".", [
                'source_form_id' => $source->id,
            ]);

            return $copy;
        });
    }

    public function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = rtrim(Str::limit(Str::slug($base), 90, ''), '-') ?: 'form';
        $candidate = $slug;
        $suffix = 2;

        while (Form::query()->where('slug', $candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = $slug.'-'.$suffix++;
        }

        return $candidate;
    }
}
