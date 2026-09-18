<?php

namespace Database\Factories;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'certificate_template_id' => CertificateTemplate::factory(),
            'certificate_number' => 'IEEE-IUBAT-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'codeword' => bin2hex(random_bytes(32)),
            'recipient_name' => $name,
            'data' => ['recipient_name' => $name],
            'template_snapshot' => ['fields' => []],
            'layout_snapshot' => ['fields' => []],
            'status' => CertificateStatus::Active,
            'issued_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
