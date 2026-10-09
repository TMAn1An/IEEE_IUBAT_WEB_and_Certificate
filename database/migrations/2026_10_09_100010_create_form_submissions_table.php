<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per accepted public submission. `form_version` records the form's
 * `lock_version` at submission time, so a reviewer can tell which
 * definition a submission was made against. No route updates or deletes
 * these rows. See docs/FORM_BUILDER.md §Submission architecture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->unsignedInteger('form_version')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
