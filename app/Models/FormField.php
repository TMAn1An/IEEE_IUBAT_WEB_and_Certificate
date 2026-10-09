<?php

namespace App\Models;

use App\Enums\FormFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One builder element. `settings` holds only the keys
 * FormFieldType::settingKeys() whitelists for this type; `style_settings`
 * only FieldStyleSchema keys; `conditional_rules` the
 * {action, match, conditions[]} shape documented in docs/FORM_BUILDER.md
 * §Conditional logic.
 */
class FormField extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'label',
        'key',
        'type',
        'required',
        'sort_order',
        'settings',
        'style_settings',
        'conditional_rules',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => FormFieldType::class,
            'required' => 'boolean',
            'sort_order' => 'integer',
            'settings' => 'array',
            'style_settings' => 'array',
            'conditional_rules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return HasMany<FormSubmissionValue, $this> */
    public function submissionValues(): HasMany
    {
        return $this->hasMany(FormSubmissionValue::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /** @return list<array{label: string, value: string}> */
    public function options(): array
    {
        return array_values($this->setting('options', []) ?? []);
    }

    /** option value => option label */
    public function optionLabels(): array
    {
        $map = [];
        foreach ($this->options() as $option) {
            $map[(string) $option['value']] = (string) $option['label'];
        }

        return $map;
    }

    public function isDisabled(): bool
    {
        return (bool) $this->setting('disabled', false);
    }

    public function isReadOnly(): bool
    {
        return (bool) $this->setting('read_only', false);
    }

    /** True when the server, not the visitor, decides this field's stored value. */
    public function usesServerValue(): bool
    {
        return $this->type === FormFieldType::Hidden || $this->isReadOnly();
    }
}
