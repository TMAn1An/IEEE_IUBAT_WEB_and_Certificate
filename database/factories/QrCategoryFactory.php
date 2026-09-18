<?php

namespace Database\Factories;

use App\Models\QrCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<QrCategory> */
class QrCategoryFactory extends Factory
{
    protected $model = QrCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true).' QR Category';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'event_name' => fake()->company(),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
