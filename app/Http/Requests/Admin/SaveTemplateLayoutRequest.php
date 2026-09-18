<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a full designer "Save Layout" submission — page dimensions,
 * every dynamic field's position/style, and the two system elements'
 * layout. See docs/CERTIFICATE_SYSTEM.md §Coordinate system for the units
 * (PDF points, bottom-left origin) and §Server-side validation for why the
 * bounds below exist: the browser's reported numbers are never trusted as
 * inherently sane, regardless of what the editor's own UI prevents.
 */
class SaveTemplateLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageLayout', $this->route('template'));
    }

    /**
     * The designer posts one JSON blob (`layout_json`, built client-side —
     * see public/js/admin/template-designer.js) rather than dozens of
     * hand-built nested form fields. Decoding it here, before validation
     * runs, lets the rules below use normal Laravel nested-array syntax
     * (`fields.*.position.x`) as if it had been a native nested form post.
     */
    protected function prepareForValidation(): void
    {
        $decoded = json_decode((string) $this->input('layout_json'), true);

        $this->merge(is_array($decoded) ? $decoded : ['fields' => []]);
    }

    public function rules(): array
    {
        $styleRules = [
            'style' => ['nullable', 'array'],
            'style.font_size' => ['nullable', 'numeric', 'min:4', 'max:300'],
            'style.font_weight' => ['nullable', Rule::in(['normal', 'bold'])],
            'style.alignment' => ['nullable', Rule::in(['left', 'center', 'right'])],
            'style.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];

        return [
            'page_width' => ['required', 'numeric', 'min:1', 'max:5000'],
            'page_height' => ['required', 'numeric', 'min:1', 'max:5000'],

            'fields' => ['present', 'array'],
            'fields.*.id' => ['required', 'integer'],
            'fields.*.position' => ['required', 'array'],
            'fields.*.position.x' => ['required', 'numeric'],
            'fields.*.position.y' => ['required', 'numeric'],
            'fields.*.position.width' => ['required', 'numeric', 'min:2'],
            'fields.*.position.height' => ['required', 'numeric', 'min:2'],
            'fields.*.style' => ['nullable', 'array'],
            'fields.*.style.font_size' => $styleRules['style.font_size'],
            'fields.*.style.font_weight' => $styleRules['style.font_weight'],
            'fields.*.style.alignment' => $styleRules['style.alignment'],
            'fields.*.style.line_height' => ['nullable', 'numeric', 'min:0.5', 'max:4'],
            'fields.*.style.color' => $styleRules['style.color'],
            'fields.*.style.wrap' => ['nullable', 'boolean'],

            'certificate_number' => ['nullable', 'array'],
            'certificate_number.x' => ['required_with:certificate_number', 'numeric'],
            'certificate_number.y' => ['required_with:certificate_number', 'numeric'],
            'certificate_number.width' => ['required_with:certificate_number', 'numeric', 'min:2'],
            'certificate_number.height' => ['required_with:certificate_number', 'numeric', 'min:2'],
            'certificate_number.style' => $styleRules['style'],
            'certificate_number.style.font_size' => $styleRules['style.font_size'],
            'certificate_number.style.font_weight' => $styleRules['style.font_weight'],
            'certificate_number.style.alignment' => $styleRules['style.alignment'],
            'certificate_number.style.color' => $styleRules['style.color'],

            'qr_code' => ['nullable', 'array'],
            'qr_code.x' => ['required_with:qr_code', 'numeric'],
            'qr_code.y' => ['required_with:qr_code', 'numeric'],
            'qr_code.width' => ['required_with:qr_code', 'numeric', 'min:10'],
            'qr_code.height' => ['required_with:qr_code', 'numeric', 'min:10'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $pageWidth = (float) $this->input('page_width');
            $pageHeight = (float) $this->input('page_height');

            if ($pageWidth <= 0 || $pageHeight <= 0) {
                return; // already flagged by the base rules
            }

            $tolerance = 20; // pt — a small off-page margin is normal (bleed, edge-anchored text)

            $checkBox = function (string $field, ?array $box) use ($validator, $pageWidth, $pageHeight, $tolerance) {
                if (! $box) {
                    return;
                }

                $x = $box['x'] ?? 0;
                $y = $box['y'] ?? 0;
                $width = $box['width'] ?? 0;
                $height = $box['height'] ?? 0;

                if ($x < -$tolerance || $x > $pageWidth + $tolerance) {
                    $validator->errors()->add($field, 'Position is far outside the certificate canvas.');
                }
                if ($y < -$tolerance || $y > $pageHeight + $tolerance) {
                    $validator->errors()->add($field, 'Position is far outside the certificate canvas.');
                }
                if ($width > $pageWidth + $tolerance || $height > $pageHeight + $tolerance) {
                    $validator->errors()->add($field, 'Size is larger than the certificate canvas.');
                }
            };

            foreach ($this->input('fields', []) as $index => $field) {
                $checkBox("fields.{$index}.position", $field['position'] ?? null);
            }
            $checkBox('certificate_number', $this->input('certificate_number'));
            $checkBox('qr_code', $this->input('qr_code'));
        });
    }
}
