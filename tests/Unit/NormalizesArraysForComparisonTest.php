<?php

namespace Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use Tests\Concerns\NormalizesArraysForComparison;
use Tests\TestCase;

/**
 * Direct coverage for the normalization helper used by FormBuilderTest to
 * compare MySQL-JSON-column-backed arrays without being sensitive to key
 * order, while staying strict everywhere assertSame is strict.
 */
class NormalizesArraysForComparisonTest extends TestCase
{
    use NormalizesArraysForComparison;

    public function test_reordered_object_keys_pass_including_nested_objects(): void
    {
        $expected = [
            'action' => 'show',
            'match' => 'all',
            'style' => ['color' => 'red', 'width' => 50],
        ];
        $actual = [
            'style' => ['width' => 50, 'color' => 'red'],
            'match' => 'all',
            'action' => 'show',
        ];

        $this->assertSameNormalized($expected, $actual);
    }

    public function test_a_scalar_type_change_fails_strict_comparison(): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->assertSameNormalized(['width' => 1], ['width' => '1']);
    }

    public function test_reordered_list_items_remain_unequal(): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->assertSameNormalized(
            ['conditions' => [['field' => 'a'], ['field' => 'b']]],
            ['conditions' => [['field' => 'b'], ['field' => 'a']]],
        );
    }

    public function test_identical_scalars_and_values_still_pass(): void
    {
        $this->assertSameNormalized(['a' => 1, 'b' => true, 'c' => null, 'd' => 'x'], ['b' => true, 'd' => 'x', 'a' => 1, 'c' => null]);
    }
}
