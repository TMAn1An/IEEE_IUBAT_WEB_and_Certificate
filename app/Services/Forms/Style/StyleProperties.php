<?php

namespace App\Services\Forms\Style;

use Illuminate\Validation\Rule;

/**
 * Shared mechanics for the two style schemas (form-level, field-level).
 * A style property is one of three kinds, and ONLY these kinds -- nothing
 * an admin types ever reaches a stylesheet as free text:
 *
 *   color -- `#rrggbb` only (what <input type="color"> produces)
 *   px    -- integer, clamped to the property's min..max, emitted as `{n}px`
 *   enum  -- a key from a fixed map; the CSS value emitted is the map's
 *            server-side value, never the submitted string
 *
 * Every property is emitted as a CSS custom property (`--ff-*`) that the
 * shared stylesheet (public/css/forms.css) consumes, so the builder's live
 * preview and the public page render through exactly the same CSS.
 */
trait StyleProperties
{
    public const COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /** @param  array<string, mixed>  $def
     * @return list<mixed> */
    protected static function rulesFor(array $def): array
    {
        return match ($def['type']) {
            'color' => ['nullable', 'string', 'regex:'.self::COLOR_PATTERN],
            'px' => ['nullable', 'integer', 'min:'.$def['min'], 'max:'.$def['max']],
            'enum' => ['nullable', 'string', Rule::in(array_map('strval', array_keys($def['options'])))],
        };
    }

    /** Returns the cleaned value, or null when the value is not acceptable. @param  array<string, mixed>  $def */
    protected static function cleanValue(array $def, mixed $value): string|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($def['type']) {
            'color' => is_string($value) && preg_match(self::COLOR_PATTERN, $value) ? strtolower($value) : null,
            'px' => is_numeric($value) && (int) $value == $value && $value >= $def['min'] && $value <= $def['max'] ? (int) $value : null,
            'enum' => (is_string($value) || is_int($value)) && array_key_exists((string) $value, $def['options']) ? (string) $value : null,
        };
    }

    /** @param  array<string, mixed>  $def */
    protected static function cssValue(array $def, string|int $value): string
    {
        return match ($def['type']) {
            'color' => (string) $value,
            'px' => $value.'px',
            'enum' => $def['options'][(string) $value]['css'],
        };
    }
}
