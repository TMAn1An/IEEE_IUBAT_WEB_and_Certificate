<?php

namespace Database\Factories;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FormField> */
class FormFieldFactory extends Factory
{
    protected $model = FormField::class;

    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'label' => 'Full name',
            'key' => 'full_name_'.fake()->unique()->numberBetween(1, 99999),
            'type' => FormFieldType::Text,
            'required' => false,
            'sort_order' => 1,
            'settings' => [],
            'style_settings' => [],
            'conditional_rules' => null,
            'is_active' => true,
        ];
    }
}
