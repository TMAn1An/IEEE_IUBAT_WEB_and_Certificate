<?php

namespace Database\Factories;

use App\Enums\CertificateTemplateStatus;
use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CertificateTemplate>
 */
class CertificateTemplateFactory extends Factory
{
    protected $model = CertificateTemplate::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true).' Certificate';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'status' => CertificateTemplateStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => CertificateTemplateStatus::Active]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => CertificateTemplateStatus::Archived]);
    }
}
