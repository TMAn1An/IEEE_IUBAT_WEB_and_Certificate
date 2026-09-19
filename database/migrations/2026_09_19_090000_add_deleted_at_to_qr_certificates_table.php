<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft delete only -- no certificate/QR row is ever hard-deleted through the
 * normal application. A record can only reach `deleted_at != null` via
 * DeletionRequestService::approve(), itself only reachable after an
 * approved certificate_deletion_requests row -- see
 * docs/CERTIFICATE_SYSTEM.md §Controlled deletion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_certificates', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('qr_certificates', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
