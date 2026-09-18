<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5. `certificate_number`, `codeword`, `recipient_name` and `data`
 * already existed (Phase 2 schema, built for exactly this) — see
 * docs/DATABASE_DESIGN.md. `codeword` is what Phase 5's spec calls the
 * "verification token"; `data` is what it calls "field_values". Both are
 * reused rather than duplicated. Only genuinely new concepts are added here:
 * where the generated PDF lives, and the two immutability snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('data');
            // Field definitions (label, field_key, field_type, options,
            // is_recipient_name) as they existed at issuance time — NOT
            // position/style. See docs/CERTIFICATE_SYSTEM.md §Snapshot strategy.
            $table->json('template_snapshot')->nullable()->after('pdf_path');
            // Exactly where everything was drawn at issuance time: each
            // field's position/style plus certificate_number_layout,
            // qr_code_layout, and the page dimensions actually used.
            $table->json('layout_snapshot')->nullable()->after('template_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'template_snapshot', 'layout_snapshot']);
        });
    }
};
