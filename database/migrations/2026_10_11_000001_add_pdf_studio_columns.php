<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDF Studio direct integration (see docs/PDF_STUDIO_INTEGRATION.md). Additive
 * only — every column is nullable, nothing here changes the behaviour of the
 * existing designer path (`certificate_number_layout`/`qr_code_layout`/
 * `template_fields`) or the manual PDF Editor Bridge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            // The admin's saved .pdftemplate bundle (PDF + fonts + layout),
            // the editor's own unmodified format — see docs/PDF_STUDIO_INTEGRATION.md.
            $table->string('editor_project_path')->nullable()->after('qr_code_layout');
            // Derived from the bundle's fieldMapping at save time: the exact
            // column names the template expects, their required-ness, and
            // which field is the QR placement — cached so every
            // suggested-Excel/validate request doesn't need to re-open the
            // bundle's ZIP.
            $table->json('editor_schema')->nullable()->after('editor_project_path');
            // Bumped on every project save; copied onto certificate_batches
            // at reserve time so a later template edit can never change what
            // an in-flight or already-completed batch rendered.
            $table->unsignedInteger('editor_schema_version')->default(0)->after('editor_schema');
        });

        Schema::table('certificate_batches', function (Blueprint $table) {
            // A client-supplied key (one per "Generate" click) so retrying
            // the same request after a lost response reuses the same batch
            // instead of reserving a second one. Nullable: the pre-existing
            // manual bridge flow never sets this.
            $table->string('idempotency_key')->nullable()->unique()->after('source');
            $table->unsignedInteger('editor_schema_version')->nullable()->after('idempotency_key');
        });

        Schema::table('certificate_batch_reservations', function (Blueprint $table) {
            // Participant photo for this row (photo field), copied into the
            // batch's own private storage at confirm time — never read back
            // from the original upload, which is a temp/prepare-only path.
            $table->string('photo_path')->nullable()->after('qr_filename');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_batch_reservations', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
        Schema::table('certificate_batches', function (Blueprint $table) {
            $table->dropColumn(['idempotency_key', 'editor_schema_version']);
        });
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn(['editor_project_path', 'editor_schema', 'editor_schema_version']);
        });
    }
};
