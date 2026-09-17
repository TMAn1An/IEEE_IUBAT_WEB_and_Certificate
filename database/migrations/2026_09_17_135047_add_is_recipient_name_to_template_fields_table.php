<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_fields', function (Blueprint $table) {
            // Which field (if any) supplies certificates.recipient_name at
            // generation time (a later phase). At most one per template,
            // and only a `text` field may carry it — enforced in
            // App\Services\Templates\TemplateFieldService, not at the DB
            // level (a partial-unique-index equivalent isn't portable
            // across MySQL versions without a generated column, and the
            // service already needs a transaction for the "unset the
            // previous holder" step regardless).
            $table->boolean('is_recipient_name')->default(false)->after('show_on_verification');
        });
    }

    public function down(): void
    {
        Schema::table('template_fields', function (Blueprint $table) {
            $table->dropColumn('is_recipient_name');
        });
    }
};
