<?php

namespace App\Services\Forms;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Forms\Style\FieldStyleSchema;
use App\Services\Forms\Style\FormStyleSchema;

/**
 * Assembles everything the renderer partial (resources/views/forms/
 * _renderer.blade.php) needs, so the Blade stays presentation-only:
 *   - the form's generated design CSS + its scoped custom CSS,
 *   - sanitized custom HTML / HTML-block content (sanitized again here at
 *     render time, on top of the save-time pass),
 *   - per-field inline style overrides and width classes,
 *   - the conditional-logic JSON consumed by form-runtime.js.
 */
class FormPresenter
{
    public function __construct(
        private readonly FormHtmlSanitizer $html,
        private readonly FormCssScoper $css,
    ) {}

    /** @return array<string, mixed> */
    public function present(Form $form): array
    {
        $fields = $form->activeFields()->get();
        $scope = '#'.$form->wrapperId();

        return [
            'form' => $form,
            'wrapperId' => $form->wrapperId(),
            // Values are validated, enum-mapped CSS (never free text); the
            // custom CSS is scoped and has `<` escaped -- see FormCssScoper.
            'styleSheet' => trim($scope.'{'.FormStyleSchema::cssDeclarations($form->style_settings).'}'."\n".$this->css->scope($form->custom_css, $scope)),
            'htmlBefore' => $this->html->sanitize($form->custom_html_before),
            'htmlAfter' => $this->html->sanitize($form->custom_html_after),
            'fields' => $fields->map(fn (FormField $field) => $this->presentField($form, $field))->all(),
            'logic' => $fields->map(fn (FormField $field) => [
                'key' => $field->key,
                'type' => $field->type->value,
                'collectsValue' => $field->type->collectsValue(),
                'rules' => $field->conditional_rules,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentField(Form $form, FormField $field): array
    {
        $width = (int) $field->setting('width', 100);

        return [
            'model' => $field,
            'type' => $field->type,
            'key' => $field->key,
            'inputId' => 'ff-'.$form->id.'-'.$field->key,
            'name' => $field->type->isMultiValue() ? $field->key.'[]' : $field->key,
            'widthClass' => $width === 100 ? '' : 'ff-w-'.$width,
            'cssClass' => (string) $field->setting('css_class', ''),
            'inlineStyle' => FieldStyleSchema::cssDeclarations($field->style_settings),
            'html' => $field->type === FormFieldType::Html ? $this->html->sanitize($field->setting('content')) : null,
            // Re-checked against their enums here because both are emitted
            // into markup (a tag name, a style value), not escaped text.
            'headingLevel' => in_array($field->setting('heading_level'), ['h2', 'h3', 'h4'], true) ? $field->setting('heading_level') : 'h3',
            'textAlign' => in_array($field->setting('text_align'), ['left', 'center', 'right'], true) ? $field->setting('text_align') : null,
        ];
    }
}
