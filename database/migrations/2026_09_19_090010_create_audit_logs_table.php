<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only. No route/controller/policy in this codebase ever updates or
 * deletes a row here -- see App\Policies\AuditLogPolicy and
 * docs/CERTIFICATE_SYSTEM.md §Controlled deletion. Only `created_at` exists
 * (no `updated_at`) -- App\Models\AuditLog sets `const UPDATED_AT = null`,
 * Laravel's documented way to skip maintaining a column that would only
 * ever mislead a reader into thinking these rows are ever modified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('record_type');
            $table->unsignedBigInteger('record_id');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            // Snapshot of the actor's role AT THE TIME of the action -- a
            // later role change must never rewrite what an old log entry
            // says the actor's authority was.
            $table->string('actor_role');
            $table->foreignId('deletion_request_id')->nullable()->constrained('certificate_deletion_requests')->nullOnDelete();
            $table->string('summary');
            $table->json('snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['record_type', 'record_id']);
            $table->index('event_type');
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
