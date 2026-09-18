<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            // Asset metadata for source_pdf_path (added Phase 2, unused until now). The
            // server-generated storage filename is never derived from this — see
            // docs/SECURITY.md §File upload safety.
            $table->string('original_filename')->nullable()->after('source_pdf_path');
            $table->string('file_mime')->nullable()->after('original_filename');
            $table->unsignedInteger('file_size')->nullable()->after('file_mime');

            // Layout for the two system-managed elements. Deliberately NOT rows in
            // template_fields (the brief is explicit: certificate_number/qr_code are
            // layout elements, never ordinary input fields) and deliberately NOT a new
            // table — there are exactly two of these per template, always, so a fixed
            // pair of nullable JSON columns on the template itself is the simplest
            // correct fit. See docs/CERTIFICATE_SYSTEM.md §System element layout storage.
            $table->json('certificate_number_layout')->nullable()->after('page_height');
            $table->json('qr_code_layout')->nullable()->after('certificate_number_layout');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn([
                'original_filename', 'file_mime', 'file_size',
                'certificate_number_layout', 'qr_code_layout',
            ]);
        });
    }
};
