<?php

namespace App\Services\Forms;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormSubmissionValue;
use App\Services\Forms\Style\FieldStyleSchema;
use App\Services\Forms\Style\FormStyleSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates and normalizes a complete builder "save" payload. Nothing the
 * builder sends is trusted: every key is validated, then normalize()
 * rebuilds the definition from whitelisted keys only, so the stored JSON
 * can never carry anything the schemas don't define. Used by
 * App\Http\Requests\Admin\Forms\SaveFormDefinitionRequest (rules/after)
 * and Admin\Forms\FormController::update (normalize).
 *
 * Payload shape (JSON body):
 *   {version, intent: "save"|"publish", autosave, name, slug, description,
 *    settings{}, style_settings{}, [custom_css, custom_html_before,
 *    custom_html_after, custom_js -- super_admin only],
 *    fields: [{id|null, type, label, key, required, is_active,
 *              settings{}, style_settings{}, conditional_rules{}|null}]}
 * `fields` is the COMPLETE ordered list (archived fields included);
 * a stored field missing from it is removed -- archived if it already has
 * submissions, otherwise deleted. See docs/FORM_BUILDER.md §Builder architecture.
 */
final class FormDefinitionValidator
{
    public const MAX_FIELDS = 200;

    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    public const CUSTOM_CODE_KEYS = ['custom_css', 'custom_html_before', 'custom_html_after', 'custom_js'];

    public function __construct(private readonly FormHtmlSanitizer $html) {}

    /** @return array<string, list<mixed>> */
    public function rules(Form $form, mixed $fields, bool $canManageCustomCode): array
    {
        $customCode = $canManageCustomCode ? 'nullable' : 'prohibited';

        $rules = [
            'version' => ['required', 'integer'],
            'intent' => ['nullable', Rule::in(['save', 'publish'])],
            'autosave' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('forms', 'slug')->ignore($form->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'custom_css' => [$customCode, 'string', 'max:'.FormCssScoper::MAX_LENGTH],
            'custom_html_before' => [$customCode, 'string', 'max:'.FieldSettingsSchema::MAX_HTML_LENGTH],
            'custom_html_after' => [$customCode, 'string', 'max:'.FieldSettingsSchema::MAX_HTML_LENGTH],
            'custom_js' => [$customCode, 'string', 'max:20000'],
            'fields' => ['present', 'array', 'max:'.self::MAX_FIELDS],
            'fields.*' => ['array'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.type' => ['required', Rule::enum(FormFieldType::class)],
            'fields.*.label' => ['nullable', 'string', 'max:255'],
            'fields.*.key' => ['required', 'string', 'regex:'.self::KEY_PATTERN],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.is_active' => ['nullable', 'boolean'],
            'fields.*.conditional_rules' => ['nullable', 'array'],
            'fields.*.conditional_rules.action' => ['nullable', Rule::in(['show', 'hide'])],
            'fields.*.conditional_rules.match' => ['nullable', Rule::in(['all', 'any'])],
            'fields.*.conditional_rules.conditions' => ['nullable', 'array', 'max:10'],
            'fields.*.conditional_rules.conditions.*.field' => ['required', 'string'],
            'fields.*.conditional_rules.conditions.*.operator' => ['required', Rule::in(FormVisibilityResolver::OPERATORS)],
            'fields.*.conditional_rules.conditions.*.value' => ['nullable', 'string', 'max:255'],
        ];

        $rules += FormSettingsSchema::rules();
        $rules += FormStyleSchema::rules();

        // Type-specific settings rules, built from each field's declared type.
        foreach (is_array($fields) ? $fields : [] as $index => $field) {
            $type = is_array($field) ? FormFieldType::tryFrom((string) ($field['type'] ?? '')) : null;
            if ($type === null) {
                continue;
            }
            $rules += FieldSettingsSchema::rules($type, "fields.{$index}.settings");
            $rules += FieldStyleSchema::rules("fields.{$index}.style_settings");
        }

        return $rules;
    }

    /** Cross-field checks that per-key rules can't express. */
    public function after(Validator $validator, Form $form, array $data): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return; // structural errors first; cross-field checks assume a well-formed payload
        }

        $fields = array_values($data['fields'] ?? []);
        $existing = $form->fields()->get()->keyBy('id');
        $withSubmissions = $this->fieldIdsWithSubmissions($form);
        $seenKeys = [];
        $seenIds = [];
        $activeIndexByKey = [];

        foreach ($fields as $index => $field) {
            $type = FormFieldType::from($field['type']);
            $key = $field['key'];
            $path = "fields.{$index}";
            $id = isset($field['id']) ? (int) $field['id'] : null;

            if (isset($seenKeys[$key])) {
                $validator->errors()->add("{$path}.key", "The key \"{$key}\" is used by more than one field. Keys must be unique within a form.");
            }
            $seenKeys[$key] = true;

            if ($id !== null) {
                if (! $existing->has($id) || isset($seenIds[$id])) {
                    // Never trust a field id from the client: it must be one of THIS form's fields.
                    $validator->errors()->add("{$path}.id", 'This field does not belong to this form.');

                    continue;
                }
                $seenIds[$id] = true;

                if (isset($withSubmissions[$id]) && $existing[$id]->key !== $key) {
                    $validator->errors()->add("{$path}.key", "The key of \"{$existing[$id]->label}\" can't change: it already has submissions stored under \"{$existing[$id]->key}\".");
                }
            }

            if ($type->collectsValue() && trim((string) ($field['label'] ?? '')) === '') {
                $validator->errors()->add("{$path}.label", 'Every input field needs a label.');
            }

            $this->checkSettings($validator, $type, $field['settings'] ?? [], $path);

            if ($field['is_active'] ?? true) {
                $this->checkConditions($validator, $field, $index, $fields, $activeIndexByKey, $path);
                $activeIndexByKey[$key] = $index;
            }
        }

        // A removed field that already has submissions is archived, not
        // deleted -- so its key stays reserved for historical exports.
        foreach ($existing as $id => $field) {
            if (! isset($seenIds[$id]) && isset($withSubmissions[$id]) && isset($seenKeys[$field->key])) {
                $validator->errors()->add('fields', "The key \"{$field->key}\" belongs to an archived field with submissions and can't be reused.");
            }
        }

        $opensAt = $data['settings']['opens_at'] ?? null;
        $closesAt = $data['settings']['closes_at'] ?? null;
        if ($opensAt && $closesAt && strcmp($closesAt, $opensAt) <= 0) {
            // Same fixed Y-m-d\TH:i format on both sides, so string order is time order.
            $validator->errors()->add('settings.closes_at', 'The closing time must be after the opening time.');
        }

        if (($data['intent'] ?? 'save') === 'publish') {
            $hasInput = collect($fields)->contains(fn ($f) => ($f['is_active'] ?? true) && FormFieldType::from($f['type'])->acceptsUserInput());
            if (! $hasInput) {
                $validator->errors()->add('fields', 'Add at least one input field before publishing.');
            }
        }
    }

    private function checkSettings(Validator $validator, FormFieldType $type, array $settings, string $path): void
    {
        if ($type->hasOptions()) {
            $values = array_map(fn ($o) => trim((string) $o['value']), $settings['options'] ?? []);
            if (count($values) !== count(array_unique($values))) {
                $validator->errors()->add("{$path}.settings.options", 'Option values must be unique.');
            }
            $default = $settings['default_value'] ?? null;
            if ($default !== null && $default !== '' && ! in_array((string) $default, $values, true)) {
                $validator->errors()->add("{$path}.settings.default_value", 'The default value must be one of the options.');
            }
        }

        $pattern = $settings['pattern'] ?? null;
        if (is_string($pattern) && $pattern !== '' && @preg_match(self::patternRegex($pattern), '') === false) {
            $validator->errors()->add("{$path}.settings.pattern", 'The pattern is not a valid regular expression.');
        }

        $min = $settings['min'] ?? null;
        $max = $settings['max'] ?? null;
        if ($min !== null && $min !== '' && $max !== null && $max !== '') {
            $inverted = $type === FormFieldType::Number ? (float) $min > (float) $max : strcmp((string) $min, (string) $max) > 0;
            if ($inverted) {
                $validator->errors()->add("{$path}.settings.max", 'The maximum must not be lower than the minimum.');
            }
        }
    }

    /** @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, int>  $activeIndexByKey  keys of active fields BEFORE this one */
    private function checkConditions(Validator $validator, array $field, int $index, array $fields, array $activeIndexByKey, string $path): void
    {
        foreach ($field['conditional_rules']['conditions'] ?? [] as $c => $condition) {
            $ref = $condition['field'];
            $conditionPath = "{$path}.conditional_rules.conditions.{$c}.field";

            if ($ref === $field['key']) {
                $validator->errors()->add($conditionPath, 'A field cannot depend on itself.');
            } elseif (! isset($activeIndexByKey[$ref])) {
                $validator->errors()->add($conditionPath, "Condition on \"{$field['key']}\" references \"{$ref}\", which is not an active field placed above it.");
            } elseif (! FormFieldType::from($fields[$activeIndexByKey[$ref]]['type'])->canDriveConditions()) {
                $validator->errors()->add($conditionPath, "\"{$ref}\" can't be used in a condition (only fields a visitor fills in can).");
            }
        }
    }

    /**
     * Rebuilds the definition from whitelisted keys only.
     *
     * @param  array<string, mixed>  $data  validated payload
     * @return array<string, mixed>
     */
    public function normalize(array $data, bool $canManageCustomCode): array
    {
        // validated() rebuilds arrays in RULE order, not input order, so
        // restore the builder's order from the original numeric indexes.
        $input = $data['fields'] ?? [];
        ksort($input);

        $fields = [];
        foreach (array_values($input) as $index => $field) {
            $type = FormFieldType::from($field['type']);
            $settings = FieldSettingsSchema::normalize($type, $field['settings'] ?? null);

            if ($type === FormFieldType::Html && isset($settings['content'])) {
                $settings['content'] = $this->html->sanitize($settings['content']);
            }

            $label = trim((string) ($field['label'] ?? ''));

            $fields[] = [
                'id' => isset($field['id']) ? (int) $field['id'] : null,
                'type' => $type,
                'label' => $label !== '' ? $label : $type->label(),
                'key' => $field['key'],
                'required' => $type->supportsRequired() && filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'is_active' => filter_var($field['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => $index + 1,
                'settings' => $settings,
                'style_settings' => FieldStyleSchema::normalize($field['style_settings'] ?? null),
                'conditional_rules' => $this->normalizeRules($field['conditional_rules'] ?? null),
            ];
        }

        $definition = [
            'name' => trim($data['name']),
            'slug' => $data['slug'],
            'description' => ($data['description'] ?? null) ?: null,
            'settings' => FormSettingsSchema::normalize($data['settings'] ?? null),
            'style_settings' => FormStyleSchema::normalize($data['style_settings'] ?? null),
            'fields' => $fields,
            'custom' => null,
        ];

        if ($canManageCustomCode) {
            $definition['custom'] = [
                'custom_css' => ($data['custom_css'] ?? null) ?: null,
                'custom_html_before' => $this->html->sanitize($data['custom_html_before'] ?? null) ?: null,
                'custom_html_after' => $this->html->sanitize($data['custom_html_after'] ?? null) ?: null,
                'custom_js' => ($data['custom_js'] ?? null) ?: null,
            ];
        }

        return $definition;
    }

    /** @return array<string, mixed>|null */
    private function normalizeRules(?array $rules): ?array
    {
        $raw = $rules['conditions'] ?? [];
        ksort($raw); // input order -- see normalize()

        $conditions = array_values(array_map(fn ($c) => [
            'field' => (string) $c['field'],
            'operator' => (string) $c['operator'],
            'value' => (string) ($c['value'] ?? ''),
        ], $raw));

        if ($conditions === []) {
            return null;
        }

        return [
            'action' => ($rules['action'] ?? 'show') === 'hide' ? 'hide' : 'show',
            'match' => ($rules['match'] ?? 'all') === 'any' ? 'any' : 'all',
            'conditions' => $conditions,
        ];
    }

    /** @return array<int, true> field id => true */
    public function fieldIdsWithSubmissions(Form $form): array
    {
        return FormSubmissionValue::query()
            ->whereIn('form_field_id', $form->fields()->select('id'))
            ->distinct()
            ->pluck('form_field_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /** The admin's pattern, anchored and delimited exactly as submission validation will use it. */
    public static function patternRegex(string $pattern): string
    {
        // Escape only UNescaped `/` (the delimiter); `\/` and `\\` pass through intact.
        $escaped = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            if ($pattern[$i] === '\\' && $i + 1 < $length) {
                $escaped .= $pattern[$i].$pattern[++$i];
            } else {
                $escaped .= $pattern[$i] === '/' ? '\/' : $pattern[$i];
            }
        }

        return '/^(?:'.$escaped.')$/u';
    }
}
