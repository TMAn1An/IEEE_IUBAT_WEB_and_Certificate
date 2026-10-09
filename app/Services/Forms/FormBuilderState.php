<?php

namespace App\Services\Forms;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\Forms\Style\FieldStyleSchema;
use App\Services\Forms\Style\FormStyleSchema;

/**
 * The JSON the builder (public/js/admin/form-builder.js) boots from, and
 * the fresh copy it receives after every save. Schema/meta come straight
 * from the server-side enums and schemas, so the builder can never offer a
 * setting the server would reject.
 *
 * Raw custom code is only included for users allowed to edit it; everyone
 * gets the already-scoped/sanitized `rendered` versions so the live preview
 * still matches what visitors will see.
 */
class FormBuilderState
{
    public function __construct(
        private readonly FormDefinitionValidator $definitions,
        private readonly FormHtmlSanitizer $html,
        private readonly FormCssScoper $css,
    ) {}

    /** @return array<string, mixed> */
    public function forForm(Form $form, User $user): array
    {
        $canCode = $user->can('manageCustomCode', Form::class);
        $withSubmissions = $this->definitions->fieldIdsWithSubmissions($form);
        $fields = $form->fields()->get();

        return [
            'form' => [
                'id' => $form->id,
                'name' => $form->name,
                'slug' => $form->slug,
                'description' => $form->description,
                'status' => $form->status->value,
                'status_label' => $form->status->label(),
                'version' => $form->lock_version,
                'settings' => FormSettingsSchema::normalize($form->settings),
                'style_settings' => FormStyleSchema::normalize($form->style_settings),
                'custom_css' => $canCode ? $form->custom_css : null,
                'custom_html_before' => $canCode ? $form->custom_html_before : null,
                'custom_html_after' => $canCode ? $form->custom_html_after : null,
                'custom_js' => $canCode ? $form->custom_js : null,
                'has_custom_code' => filled($form->custom_css) || filled($form->custom_html_before) || filled($form->custom_html_after) || filled($form->custom_js),
            ],
            'fields' => $fields->map(fn (FormField $field) => [
                'id' => $field->id,
                'type' => $field->type->value,
                'label' => $field->label,
                'key' => $field->key,
                'required' => $field->required,
                'is_active' => $field->is_active,
                'settings' => (object) ($field->settings ?? []),
                'style_settings' => (object) ($field->style_settings ?? []),
                'conditional_rules' => $field->conditional_rules,
                'has_submissions' => isset($withSubmissions[$field->id]),
            ])->values()->all(),
            'rendered' => [
                'css' => $this->css->scope($form->custom_css, '#'.$form->wrapperId()),
                'html_before' => $this->html->sanitize($form->custom_html_before),
                'html_after' => $this->html->sanitize($form->custom_html_after),
                'fields' => (object) $fields
                    ->filter(fn (FormField $f) => $f->type === FormFieldType::Html)
                    ->mapWithKeys(fn (FormField $f) => [$f->id => $this->html->sanitize($f->setting('content'))])
                    ->all(),
            ],
            'permissions' => [
                'canEdit' => $user->can('update', $form),
                'canManageCustomCode' => $canCode,
            ],
        ];
    }

    /** Static schema the builder renders its palette and panels from. @return array<string, mixed> */
    public function meta(): array
    {
        return [
            'types' => FormFieldType::builderMeta(),
            'formStyle' => FormStyleSchema::describeForBuilder(),
            'fieldStyle' => FieldStyleSchema::describeForBuilder(),
            'widths' => FieldSettingsSchema::WIDTHS,
            'operators' => [
                'equals' => 'equals',
                'not_equals' => 'does not equal',
                'contains' => 'contains',
                'is_empty' => 'is empty',
                'is_not_empty' => 'is not empty',
            ],
            'keyPattern' => '^[a-z][a-z0-9_]{0,63}$',
        ];
    }
}
