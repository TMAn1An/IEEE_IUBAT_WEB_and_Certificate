<?php

namespace App\Services\Forms\Style;

/**
 * Optional per-field style overrides (form_fields.style_settings). Every
 * property defaults to null = "inherit the form-level design". Each one
 * re-declares a form-level CSS custom property on the field's own wrapper,
 * so an override cascades naturally to that field only. `applies_to`
 * tells the builder which overrides to show for which kind of element.
 */
final class FieldStyleSchema
{
    use StyleProperties;

    /** @return array<string, array<string, mixed>> */
    public static function definition(): array
    {
        return [
            'label_color' => ['type' => 'color', 'label' => 'Label color', 'var' => '--ff-label-color', 'applies_to' => 'input'],
            'label_font_size' => ['type' => 'px', 'label' => 'Label font size', 'min' => 10, 'max' => 32, 'var' => '--ff-label-size', 'applies_to' => 'input'],
            'input_text_color' => ['type' => 'color', 'label' => 'Input text color', 'var' => '--ff-input-text', 'applies_to' => 'input'],
            'input_background' => ['type' => 'color', 'label' => 'Input background', 'var' => '--ff-input-bg', 'applies_to' => 'input'],
            'input_border_color' => ['type' => 'color', 'label' => 'Input border color', 'var' => '--ff-input-border', 'applies_to' => 'input'],
            'text_color' => ['type' => 'color', 'label' => 'Text color', 'var' => '--ff-text', 'applies_to' => 'content'],
            'text_size' => ['type' => 'px', 'label' => 'Text size', 'min' => 10, 'max' => 64, 'var' => '--ff-content-size', 'applies_to' => 'content'],
            'margin_bottom' => ['type' => 'px', 'label' => 'Extra space below', 'min' => 0, 'max' => 80, 'var' => '--ff-field-mb', 'applies_to' => 'all'],
        ];
    }

    /** @return array<string, list<mixed>> */
    public static function rules(string $prefix): array
    {
        $rules = [$prefix => ['nullable', 'array']];
        foreach (self::definition() as $key => $def) {
            $rules["{$prefix}.{$key}"] = self::rulesFor($def);
        }

        return $rules;
    }

    /** Known keys with valid values only; nulls (inherit) dropped. @return array<string, string|int> */
    public static function normalize(?array $styles): array
    {
        $normalized = [];
        foreach (self::definition() as $key => $def) {
            $value = self::cleanValue($def, $styles[$key] ?? null);
            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /** Inline `style` attribute value for the field wrapper, e.g. `--ff-label-color:#ff0000`. */
    public static function cssDeclarations(?array $styles): string
    {
        $definition = self::definition();
        $declarations = [];
        foreach (self::normalize($styles) as $key => $value) {
            $declarations[] = $definition[$key]['var'].':'.self::cssValue($definition[$key], $value);
        }

        return implode(';', $declarations);
    }

    /** @return array<string, mixed> */
    public static function describeForBuilder(): array
    {
        return self::definition();
    }
}
