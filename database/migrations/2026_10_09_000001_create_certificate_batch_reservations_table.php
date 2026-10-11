<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDF Editor Bridge (see docs/CERTIFICATE_SYSTEM.md §PDF Editor Bridge).
 *
 * A reservation is created BEFORE any PDF exists: it mints the real
 * certificate_number/codeword (so they can be baked into a QR image and an
 * augmented spreadsheet handed to the separate, client-side PDF Template
 * Studio editor), without ever inserting into `certificates` — this
 * repo's CertificateIssuanceService guarantees "no certificates row
 * without a matching stored PDF" (see docs/CERTIFICATE_SYSTEM.md §Failure
 * handling), and that invariant must not be weakened for this new path.
 * `certificate_id` is filled in only once the admin uploads the editor's
 * real generated PDF back and it is matched by filename==codeword.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_batch_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_index');
            $table->string('certificate_number')->unique();
            $table->string('codeword')->unique();
            $table->string('recipient_name')->nullable();
            $table->json('data');
            $table->string('qr_filename');
            $table->string('status')->default('reserved');
            $table->foreignId('certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // Bridge-created batches are distinguishable from any future
        // native bulk-generation path without touching anything else on
        // this already-existing, previously schema-only table.
        Schema::table('certificate_batches', function (Blueprint $table) {
            $table->string('source')->nullable()->after('certificate_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_batches', function (Blueprint $table) {
            $table->dropColumn('source');
        });
        Schema::dropIfExists('certificate_batch_reservations');
    }
};
