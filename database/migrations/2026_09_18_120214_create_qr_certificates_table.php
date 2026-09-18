<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The simplified QR tool's own record table -- deliberately NOT the
 * advanced system's `certificates` table, which has a NOT NULL
 * `certificate_template_id` (`restrictOnDelete`) and would force every
 * simple QR record to depend on the advanced CertificateTemplate lifecycle.
 * See docs/CERTIFICATE_SYSTEM.md §Simple QR tool for the full reasoning.
 *
 * No `certificate_number` (the old tool never had one -- it used a
 * per-Excel-file row serial, not a formatted number; see the migration
 * inspection notes in docs/CERTIFICATE_SYSTEM.md). No `pdf_path`,
 * `template_snapshot`, or `layout_snapshot` -- this tool never renders a
 * PDF and (unlike the advanced system) does not snapshot field
 * definitions at issuance; see the same doc section for why that's an
 * accepted simplification here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_category_id')->constrained()->restrictOnDelete();
            $table->string('recipient_name');
            // Denormalized from the category at creation time (or an
            // imported row's own preserved value) -- frozen per record
            // without needing a full snapshot system.
            $table->string('event_name')->nullable();
            $table->json('data');
            // 16-char uppercase alphanumeric, matching the old tool's
            // format exactly -- see App\Services\QrTool\QrToolCodewordService.
            // A different shape from the advanced system's 64-char hex
            // codeword; both are accepted by the shared public verification
            // route (VerificationCodewordService::ACCEPTED_PATTERN already
            // covers both without changes).
            $table->string('codeword')->unique();
            $table->string('status')->default('active'); // App\Enums\QrCertificateStatus
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_certificates');
    }
};
