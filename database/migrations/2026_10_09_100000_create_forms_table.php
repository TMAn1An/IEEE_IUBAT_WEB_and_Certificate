<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * General-purpose dynamic forms -- fully independent of the certificate/QR
 * systems (no foreign key into any of their tables, and none of theirs
 * points here). See docs/FORM_BUILDER.md.
 *
 * `settings` / `style_settings` are structured JSON whose allowed shape is
 * owned by App\Services\Forms\FormSettingsSchema / FormStyleSchema -- never
 * free-form blobs. `lock_version` is the optimistic-concurrency counter the
 * builder echoes back on every save, so two admins editing the same form
 * can never silently overwrite each other.
 *
 * There is no hard-delete route for forms anywhere: a form is archived,
 * never removed (its submissions must stay readable/exportable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft'); // App\Enums\FormStatus
            $table->json('settings')->nullable();
            $table->json('style_settings')->nullable();
            // "Custom Code" -- super_admin-only (App\Policies\FormPolicy::manageCustomCode).
            $table->text('custom_css')->nullable(); // stored as typed; scoped to the form's wrapper at render time
            $table->text('custom_html_before')->nullable(); // stored sanitized, re-sanitized at render time
            $table->text('custom_html_after')->nullable();
            $table->text('custom_js')->nullable(); // stored ONLY -- never rendered/executed in this phase
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
