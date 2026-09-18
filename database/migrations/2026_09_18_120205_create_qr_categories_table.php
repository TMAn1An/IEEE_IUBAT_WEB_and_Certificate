<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The simplified QR tool's category system — deliberately NOT
 * `certificate_templates`. No PDF background, no layout, no
 * draft/active/archived lifecycle, no foreign key to anything in the
 * advanced certificate system. `is_active` is a plain toggle, not an
 * activation gate with validation rules. See
 * docs/CERTIFICATE_SYSTEM.md §Simple QR tool (isolated from the advanced
 * system) for why this table exists separately from `certificate_templates`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // The old tool's "Conference/Event" value, e.g. "IEEE BECITHCON
            // 2026" -- a fixed property of the category (not chosen
            // per-submission, unlike the old tool's optional per-record
            // toggle). See docs/CERTIFICATE_SYSTEM.md for the reasoning.
            $table->string('event_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_categories');
    }
};
