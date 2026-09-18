<?php

namespace Database\Factories;

use App\Models\QrCategory;
use App\Models\QrCategoryField;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QrCategoryField> */
class QrCategoryFieldFactory extends Factory
{
    protected $model = QrCategoryField::class;

    public function definition(): array
    {
        $label = fake()->unique()->word();

        return [
            'qr_category_id' => QrCategory::factory(),
            'label' => ucfirst($label),
            'key' => $label,
            'type' => 'text',
            'required' => true,
            'sort_order' => 0,
        ];
    }
}
