<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('certificate_batch_id')->nullable()->constrained()->nullOnDelete();
            // Human-readable, e.g. IEEE-IUBAT-2026-000123 — safe to display
            // publicly. NOT the verification secret (see `codeword`).
            $table->string('certificate_number')->unique();
            // Cryptographically random verification identifier — this, not
            // the certificate number, is what the QR/verify URL uses. See
            // docs/CERTIFICATE_SYSTEM.md.
            $table->string('codeword')->unique();
            // Denormalized for admin search/listing only — `data` is
            // authoritative. See docs/DATABASE_DESIGN.md.
            $table->string('recipient_name')->nullable();
            $table->json('data');
            $table->string('status')->default('active');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->foreignId('reissued_from_id')->nullable()->constrained('certificates')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
