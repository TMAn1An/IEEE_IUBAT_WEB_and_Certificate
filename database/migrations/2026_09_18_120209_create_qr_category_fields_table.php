<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deliberately lean compared to `template_fields`: no `position`/`style`
 * JSON (no PDF placement exists in this tool at all). Otherwise mirrors the
 * concepts that already proved useful — `is_recipient_name`,
 * `show_on_verification` — so the same public verification page can serve
 * both systems uniformly. See docs/CERTIFICATE_SYSTEM.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_category_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_category_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('key'); // snake_case, unique per category -- see App\Models\QrCategoryField
            $table->string('type'); // App\Enums\QrCategoryFieldType
            $table->boolean('required')->default(true);
            $table->json('options')->nullable(); // dropdown choices
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_recipient_name')->default(false);
            $table->boolean('show_on_verification')->default(true);
            $table->timestamps();

            $table->unique(['qr_category_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_category_fields');
    }
};
