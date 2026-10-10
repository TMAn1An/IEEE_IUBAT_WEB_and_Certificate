<?php

namespace Database\Factories;

use App\Enums\CertificateBatchStatus;
use App\Models\CertificateBatch;
use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateBatch>
 */
class CertificateBatchFactory extends Factory
{
    protected $model = CertificateBatch::class;

    public function definition(): array
    {
        return [
            'certificate_template_id' => CertificateTemplate::factory(),
            'source' => 'pdf_studio',
            'name' => fake()->words(3, true).' batch',
            'status' => CertificateBatchStatus::Completed,
            'total_rows' => 3,
            'successful_rows' => 3,
            'failed_rows' => 0,
            'created_by' => User::factory(),
        ];
    }
}
