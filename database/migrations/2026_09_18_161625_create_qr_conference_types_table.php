<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persists what the old tool (IEEEQRCODEGENERATOR-main) kept only in the
 * browser's localStorage: the "Conference"/"Event" type dropdown's own
 * option list. Global, not per-category — the old tool only ever had one
 * such list, and this Laravel version's one main tool page is still bound
 * to a single category (see docs/CERTIFICATE_SYSTEM.md §Simple QR tool:
 * old-tool-parity rebuild). Removing a type never touches historical
 * `qr_certificates` rows (they store a plain string, no FK) — it only
 * stops the type being offered for future generation, matching the old
 * tool's own behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_conference_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_conference_types');
    }
};
