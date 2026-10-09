<?php

namespace Database\Factories;

use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\User;
use App\Services\Forms\FormSettingsSchema;
use App\Services\Forms\Style\FormStyleSchema;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Form> */
class FormFactory extends Factory
{
    protected $model = Form::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => null,
            'status' => FormStatus::Draft,
            'settings' => FormSettingsSchema::normalize(null),
            'style_settings' => FormStyleSchema::defaults(),
            'lock_version' => 1,
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => FormStatus::Active, 'published_at' => now()]);
    }
}
