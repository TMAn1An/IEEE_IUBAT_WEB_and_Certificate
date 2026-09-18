<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per calendar year, holding the next certificate-number sequence
 * value. A dedicated tiny table (rather than `COUNT(certificates) + 1`) is
 * what makes number generation race-condition safe under concurrent
 * issuance: CertificateNumberService uses `SELECT ... FOR UPDATE` (row lock)
 * on the row for the current year, so two concurrent requests can never
 * read/increment the same number. See docs/CERTIFICATE_SYSTEM.md §Certificate
 * number generation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_number_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('next_sequence')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_number_counters');
    }
};
