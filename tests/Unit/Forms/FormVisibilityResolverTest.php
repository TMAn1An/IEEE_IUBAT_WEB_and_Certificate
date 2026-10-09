<?php

namespace Tests\Unit\Forms;

use App\Enums\FormFieldType;
use App\Models\FormField;
use App\Services\Forms\FormVisibilityResolver;
use PHPUnit\Framework\TestCase;

/**
 * The server-side half of conditional logic. public/js/forms/form-logic.js
 * implements the same rules; keep the two in step.
 */
class FormVisibilityResolverTest extends TestCase
{
    private function field(string $key, FormFieldType $type, ?array $rules = null, array $settings = []): FormField
    {
        return new FormField(['key' => $key, 'type' => $type, 'conditional_rules' => $rules, 'settings' => $settings, 'label' => $key]);
    }

    private function rule(string $field, string $operator, string $value = '', string $action = 'show', string $match = 'all'): array
    {
        return ['action' => $action, 'match' => $match, 'conditions' => [['field' => $field, 'operator' => $operator, 'value' => $value]]];
    }

    public function test_operators(): void
    {
        $resolver = new FormVisibilityResolver;
        $cases = [
            ['equals', 'Other', ['dept' => 'other'], true],
            ['equals', 'other', ['dept' => 'cse'], false],
            ['not_equals', 'other', ['dept' => 'cse'], true],
            ['contains', 'sci', ['dept' => 'Computer Science'], true],
            ['contains', 'xyz', ['dept' => 'Computer Science'], false],
            ['is_empty', '', ['dept' => '  '], true],
            ['is_empty', '', [], true],
            ['is_not_empty', '', ['dept' => 'x'], true],
        ];

        foreach ($cases as [$operator, $value, $input, $expected]) {
            $visible = $resolver->resolve([
                $this->field('dept', FormFieldType::Text),
                $this->field('target', FormFieldType::Text, $this->rule('dept', $operator, $value)),
            ], $input);
            $this->assertSame($expected, $visible['target'], "{$operator} '{$value}'");
        }
    }

    public function test_hide_action_and_any_match(): void
    {
        $resolver = new FormVisibilityResolver;
        $fields = [
            $this->field('a', FormFieldType::Text),
            $this->field('b', FormFieldType::Text),
            $this->field('t', FormFieldType::Text, ['action' => 'hide', 'match' => 'any', 'conditions' => [
                ['field' => 'a', 'operator' => 'equals', 'value' => '1'],
                ['field' => 'b', 'operator' => 'equals', 'value' => '1'],
            ]]),
        ];

        $this->assertTrue($resolver->resolve($fields, ['a' => '0', 'b' => '0'])['t']);
        $this->assertFalse($resolver->resolve($fields, ['a' => '0', 'b' => '1'])['t']);
    }

    public function test_checkbox_group_equals_means_one_of_the_ticked_options(): void
    {
        $resolver = new FormVisibilityResolver;
        $fields = [
            $this->field('interests', FormFieldType::CheckboxGroup),
            $this->field('ai_details', FormFieldType::Text, $this->rule('interests', 'equals', 'ai')),
        ];

        $this->assertTrue($resolver->resolve($fields, ['interests' => ['robotics', 'ai']])['ai_details']);
        $this->assertFalse($resolver->resolve($fields, ['interests' => ['robotics']])['ai_details']);
    }

    public function test_hidden_fields_count_as_empty_for_later_conditions(): void
    {
        $resolver = new FormVisibilityResolver;
        $fields = [
            $this->field('a', FormFieldType::Text),
            $this->field('b', FormFieldType::Text, $this->rule('a', 'equals', 'yes')),
            $this->field('c', FormFieldType::Text, $this->rule('b', 'is_not_empty')),
        ];

        // b is hidden (a != yes) so its posted value is ignored and c stays hidden.
        $visible = $resolver->resolve($fields, ['a' => 'no', 'b' => 'posted anyway']);
        $this->assertFalse($visible['b']);
        $this->assertFalse($visible['c']);
    }

    public function test_section_visibility_cascades_until_the_next_section(): void
    {
        $resolver = new FormVisibilityResolver;
        $fields = [
            $this->field('go', FormFieldType::Text),
            $this->field('s1', FormFieldType::Section, $this->rule('go', 'equals', 'yes')),
            $this->field('inside', FormFieldType::Text),
            $this->field('s2', FormFieldType::Section),
            $this->field('after', FormFieldType::Text),
        ];

        $visible = $resolver->resolve($fields, ['go' => 'no']);
        $this->assertFalse($visible['s1']);
        $this->assertFalse($visible['inside']);
        $this->assertTrue($visible['after']);
    }

    public function test_server_value_fields_use_their_default_not_the_posted_value(): void
    {
        $resolver = new FormVisibilityResolver;
        $fields = [
            $this->field('track', FormFieldType::Hidden, null, ['default_value' => 'student']),
            $this->field('student_id', FormFieldType::Text, $this->rule('track', 'equals', 'student')),
        ];

        $this->assertTrue($resolver->resolve($fields, ['track' => 'staff'])['student_id']);
    }
}
