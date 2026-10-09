<?php

namespace App\Services\Forms\Style;

/**
 * The form-level "Design" settings (forms.style_settings): which groups and
 * properties exist, their defaults, validation, and how each maps to a CSS
 * custom property. Single source of truth for the builder's Design panel
 * (rendered from describeForBuilder()), save-time validation (rules()),
 * and render-time CSS (cssDeclarations()). See docs/FORM_BUILDER.md §Style
 * system.
 */
final class FormStyleSchema
{
    use StyleProperties;

    /** @return array<string, array{label: string, properties: array<string, array<string, mixed>>}> */
    public static function definition(): array
    {
        $px = fn (string $label, int $min, int $max, int $default, string $var) => compact('label', 'min', 'max', 'default', 'var') + ['type' => 'px'];
        $color = fn (string $label, string $default, string $var) => compact('label', 'default', 'var') + ['type' => 'color'];
        $enum = fn (string $label, array $options, string $default, string $var) => compact('label', 'options', 'default', 'var') + ['type' => 'enum'];

        return [
            'form' => [
                'label' => 'Form container',
                'properties' => [
                    'max_width' => $px('Max width', 320, 1600, 720, '--ff-max-width'),
                    'alignment' => $enum('Alignment', [
                        'left' => ['label' => 'Left', 'css' => '0 auto 0 0'],
                        'center' => ['label' => 'Center', 'css' => '0 auto'],
                        'right' => ['label' => 'Right', 'css' => '0 0 0 auto'],
                    ], 'center', '--ff-margin'),
                    'background' => $color('Background', '#ffffff', '--ff-bg'),
                    'text_color' => $color('Text color', '#1a1a1a', '--ff-text'),
                    'font_family' => $enum('Font', [
                        'inherit' => ['label' => 'Site default', 'css' => 'inherit'],
                        'sans' => ['label' => 'System sans-serif', 'css' => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif'],
                        'serif' => ['label' => 'Serif', 'css' => 'Georgia, "Times New Roman", serif'],
                        'mono' => ['label' => 'Monospace', 'css' => 'ui-monospace, Consolas, monospace'],
                    ], 'inherit', '--ff-font'),
                    'padding' => $px('Padding', 0, 96, 32, '--ff-padding'),
                    'field_gap' => $px('Space between fields', 0, 64, 20, '--ff-gap'),
                    'border_width' => $px('Border width', 0, 12, 1, '--ff-border-width'),
                    'border_color' => $color('Border color', '#dde3ea', '--ff-border-color'),
                    'border_radius' => $px('Corner radius', 0, 48, 12, '--ff-radius'),
                    'shadow' => $enum('Shadow', [
                        'none' => ['label' => 'None', 'css' => 'none'],
                        'sm' => ['label' => 'Subtle', 'css' => '0 1px 3px rgba(0,0,0,.08)'],
                        'md' => ['label' => 'Medium', 'css' => '0 4px 14px rgba(0,0,0,.10)'],
                        'lg' => ['label' => 'Strong', 'css' => '0 12px 32px rgba(0,0,0,.16)'],
                    ], 'sm', '--ff-shadow'),
                ],
            ],
            'label' => [
                'label' => 'Labels',
                'properties' => [
                    'color' => $color('Color', '#002855', '--ff-label-color'),
                    'font_size' => $px('Font size', 10, 32, 15, '--ff-label-size'),
                    'font_weight' => $enum('Weight', [
                        '400' => ['label' => 'Regular', 'css' => '400'],
                        '500' => ['label' => 'Medium', 'css' => '500'],
                        '600' => ['label' => 'Semi-bold', 'css' => '600'],
                        '700' => ['label' => 'Bold', 'css' => '700'],
                    ], '600', '--ff-label-weight'),
                    'spacing' => $px('Space below label', 0, 24, 6, '--ff-label-gap'),
                ],
            ],
            'input' => [
                'label' => 'Inputs',
                'properties' => [
                    'text_color' => $color('Text color', '#1a1a1a', '--ff-input-text'),
                    'background' => $color('Background', '#ffffff', '--ff-input-bg'),
                    'border_color' => $color('Border color', '#c5ced8', '--ff-input-border'),
                    'focus_border_color' => $color('Focus border color', '#00629b', '--ff-input-focus'),
                    'border_width' => $px('Border width', 0, 6, 1, '--ff-input-border-width'),
                    'radius' => $px('Corner radius', 0, 30, 8, '--ff-input-radius'),
                    'padding_y' => $px('Vertical padding', 2, 24, 10, '--ff-input-py'),
                    'padding_x' => $px('Horizontal padding', 4, 32, 12, '--ff-input-px'),
                    'font_size' => $px('Font size', 12, 24, 15, '--ff-input-size'),
                ],
            ],
            'button' => [
                'label' => 'Submit button',
                'properties' => [
                    'background' => $color('Background', '#00629b', '--ff-btn-bg'),
                    'hover_background' => $color('Hover background', '#002855', '--ff-btn-bg-hover'),
                    'text_color' => $color('Text color', '#ffffff', '--ff-btn-text'),
                    'radius' => $px('Corner radius', 0, 40, 8, '--ff-btn-radius'),
                    'font_size' => $px('Font size', 12, 28, 16, '--ff-btn-size'),
                    'padding_y' => $px('Vertical padding', 4, 30, 12, '--ff-btn-py'),
                    'padding_x' => $px('Horizontal padding', 8, 60, 28, '--ff-btn-px'),
                    'width' => $enum('Width', [
                        'auto' => ['label' => 'Fit text', 'css' => 'auto'],
                        'full' => ['label' => 'Full width', 'css' => '100%'],
                    ], 'auto', '--ff-btn-width'),
                    'alignment' => $enum('Alignment', [
                        'left' => ['label' => 'Left', 'css' => 'flex-start'],
                        'center' => ['label' => 'Center', 'css' => 'center'],
                        'right' => ['label' => 'Right', 'css' => 'flex-end'],
                    ], 'left', '--ff-btn-align'),
                ],
            ],
            'messages' => [
                'label' => 'Errors & messages',
                'properties' => [
                    'error_color' => $color('Error text', '#b42318', '--ff-error'),
                    'error_border_color' => $color('Error border', '#d92d20', '--ff-error-border'),
                    'success_background' => $color('Success background', '#ecfdf3', '--ff-success-bg'),
                    'success_text_color' => $color('Success text', '#05603a', '--ff-success-text'),
                    'success_border_color' => $color('Success border', '#a6f4c5', '--ff-success-border'),
                ],
            ],
        ];
    }

    /** @return array<string, array<string, string|int>> */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::definition() as $group => $groupDef) {
            foreach ($groupDef['properties'] as $key => $def) {
                $defaults[$group][$key] = $def['default'];
            }
        }

        return $defaults;
    }

    /** Laravel validation rules for a `style_settings` payload. @return array<string, list<mixed>> */
    public static function rules(string $prefix = 'style_settings'): array
    {
        $rules = [$prefix => ['nullable', 'array']];
        foreach (self::definition() as $group => $groupDef) {
            $rules["{$prefix}.{$group}"] = ['nullable', 'array'];
            foreach ($groupDef['properties'] as $key => $def) {
                $rules["{$prefix}.{$group}.{$key}"] = self::rulesFor($def);
            }
        }

        return $rules;
    }

    /**
     * Known keys only, invalid values replaced by defaults. Used both when
     * saving (after validation) and when rendering (defense in depth: a
     * value that somehow reached the DB unvalidated still never reaches CSS).
     *
     * @return array<string, array<string, string|int>>
     */
    public static function normalize(?array $styles): array
    {
        $normalized = [];
        foreach (self::definition() as $group => $groupDef) {
            foreach ($groupDef['properties'] as $key => $def) {
                $normalized[$group][$key] = self::cleanValue($def, $styles[$group][$key] ?? null) ?? $def['default'];
            }
        }

        return $normalized;
    }

    /** `--ff-bg:#ffffff;--ff-padding:32px;...` for the form wrapper. */
    public static function cssDeclarations(?array $styles): string
    {
        $normalized = self::normalize($styles);
        $declarations = [];
        foreach (self::definition() as $group => $groupDef) {
            foreach ($groupDef['properties'] as $key => $def) {
                $declarations[] = $def['var'].':'.self::cssValue($def, $normalized[$group][$key]);
            }
        }

        return implode(';', $declarations);
    }

    /** @return array<string, mixed> */
    public static function describeForBuilder(): array
    {
        return self::definition();
    }
}
