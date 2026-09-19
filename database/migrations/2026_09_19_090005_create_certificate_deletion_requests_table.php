<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The one gate a certificate/QR record must pass through before it can ever
 * be soft-deleted -- see docs/CERTIFICATE_SYSTEM.md §Controlled deletion.
 *
 * `record_type` is a string backed by App\Enums\DeletableRecordType --
 * never a raw model class name accepted from a request. `record_id` has no
 * single FK (it points into one of two different tables depending on
 * `record_type`); existence/trashed-state is re-checked in
 * DeletionRequestService at request-time and again at approval-time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('record_type');
            $table->unsignedBigInteger('record_id');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Duplicate-pending-request protection is enforced in
            // DeletionRequestService (lockForUpdate inside a transaction) --
            // MySQL has no partial/conditional unique index, so a DB-level
            // constraint can't express "only one PENDING row per record"
            // directly. This index makes that service-level check cheap.
            $table->index(['record_type', 'record_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_deletion_requests');
    }
};
