<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per submitted field value. Everything needed to read the value
 * back is snapshotted here at submission time -- key, label, type, and a
 * human-readable `display_value` (option LABELS, not just option values) --
 * so an old submission stays understandable after the admin renames a
 * field, edits its options, or archives it. `form_field_id` is kept for a
 * stable export-column mapping, but nothing requires it to still resolve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submission_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_field_id')->nullable()->constrained('form_fields')->nullOnDelete();
            $table->string('field_key', 64);
            $table->string('field_label_snapshot');
            $table->string('field_type_snapshot', 32);
            $table->longText('value')->nullable(); // JSON-encoded (scalar or list) -- see App\Models\FormSubmissionValue
            $table->longText('display_value')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['form_submission_id', 'field_key']);
            $table->index('form_field_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_values');
    }
};
