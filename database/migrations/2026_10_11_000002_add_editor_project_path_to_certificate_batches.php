<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes a batch-template-immutability gap: `certificate_batches` previously
 * stored only `editor_schema_version` (an integer), with no pointer to the
 * actual immutable project file that version corresponds to — and
 * TemplateProjectService::save() deleted the OLD project file on every
 * resave. A batch created against version 1, then resumed after the admin
 * edited and resaved the template (now version 2), would silently fetch and
 * render the NEW design for its remaining rows — found and fixed per
 * docs/PDF_STUDIO_INTEGRATION.md's "Batch template immutability" section.
 *
 * This column is nullable: batches created before this fix (if any) have no
 * recorded path and fall back to the template's current project, which is
 * the pre-fix (unsafe) behavior, not a silent data loss — documented, not
 * backfilled, since there is no way to know what the design looked like at
 * the time for a batch that didn't record it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_batches', function (Blueprint $table) {
            $table->string('editor_project_path')->nullable()->after('editor_schema_version');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_batches', function (Blueprint $table) {
            $table->dropColumn('editor_project_path');
        });
    }
};
