<?php

namespace Database\Factories;

use App\Enums\TemplateFieldType;
use App\Models\CertificateTemplate;
use App\Models\TemplateField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TemplateField>
 */
class TemplateFieldFactory extends Factory
{
    protected $model = TemplateField::class;

    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return [
            'certificate_template_id' => CertificateTemplate::factory(),
            'label' => Str::title($label),
            'field_key' => Str::snake($label),
            'field_type' => TemplateFieldType::Text,
            'is_required' => true,
            'show_on_verification' => true,
            'is_recipient_name' => false,
            'options' => null,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
