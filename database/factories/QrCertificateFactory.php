<?php

namespace Database\Factories;

use App\Enums\QrCertificateStatus;
use App\Models\QrCategory;
use App\Models\QrCertificate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QrCertificate> */
class QrCertificateFactory extends Factory
{
    protected $model = QrCertificate::class;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'qr_category_id' => QrCategory::factory(),
            'recipient_name' => $name,
            'event_name' => fake()->company(),
            'data' => ['recipient_name' => $name],
            'codeword' => fake()->unique()->regexify('[A-Z0-9]{16}'),
            'status' => QrCertificateStatus::Active,
            'created_by' => User::factory(),
        ];
    }
}
