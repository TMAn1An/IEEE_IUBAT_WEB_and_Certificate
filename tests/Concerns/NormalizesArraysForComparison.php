<?php

namespace Tests\Concerns;

/**
 * For asserting two arrays are equal regardless of associative/object key
 * order, while still being as strict as assertSame everywhere else: exact
 * scalar values AND types (no 1 == "1"), and exact list element order
 * (a reordered list is a different list, not just a different key order).
 *
 * MySQL's native JSON column type doesn't guarantee member key order on
 * round-trip, so a plain assertSame() on a decoded JSON column is sensitive
 * to an ordering the application's contract never promised. assertEquals()
 * is not the fix: PHPUnit's assertEquals uses loose (==) comparison, so it
 * would also pass a type change like `1` vs `"1"` or `true` vs `1`, which
 * normalize() + assertSame() here does not allow.
 */
trait NormalizesArraysForComparison
{
    /**
     * Recursively sorts associative/object array keys so key order doesn't
     * matter; list arrays (sequential integer keys from 0) are returned
     * with their element order untouched, only recursing into each element.
     * Scalars and their types are never altered.
     */
    protected static function normalizeForComparison(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(static fn ($item) => self::normalizeForComparison($item), $value);
        }

        ksort($value);

        return array_map(static fn ($item) => self::normalizeForComparison($item), $value);
    }

    /**
     * assertSame() after normalizing both sides' associative-array key
     * order. Still strict on scalar values/types and on list element order.
     */
    protected static function assertSameNormalized(mixed $expected, mixed $actual, string $message = ''): void
    {
        static::assertSame(
            self::normalizeForComparison($expected),
            self::normalizeForComparison($actual),
            $message,
        );
    }
}
