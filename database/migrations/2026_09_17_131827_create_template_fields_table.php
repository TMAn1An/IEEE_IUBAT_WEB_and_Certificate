<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_template_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            // snake_case, unique per template — the key used in a
            // certificate's `data` JSON and (later) the Excel column header.
            $table->string('field_key');
            $table->string('field_type');
            $table->boolean('is_required')->default(true);
            $table->boolean('show_on_verification')->default(true);
            $table->string('verification_label')->nullable();
            // Dropdown choices, e.g. ["Session Chair","Invited Speaker"].
            $table->json('options')->nullable();
            // Editor placement in PDF points (x/y/width/height) — see
            // docs/TEMPLATE_EDITOR.md §Coordinate conversion. Not populated
            // until the template editor (Phase 4) exists.
            $table->json('position')->nullable();
            // font_family/font_size/font_weight/text_align/text_color.
            $table->json('style')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['certificate_template_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_fields');
    }
};
