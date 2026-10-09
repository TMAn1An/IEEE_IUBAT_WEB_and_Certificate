<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per builder element (input fields AND layout/content blocks like
 * heading/divider/HTML). `key` is the stable machine identifier submissions
 * are stored under; it is unique per form INCLUDING archived fields, so a
 * new field can never reuse the key of an archived one and blur historical
 * exports. A field that already has submission values is never
 * hard-deleted -- it is archived (`is_active = false`). See
 * docs/FORM_BUILDER.md §Historical snapshot strategy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->restrictOnDelete();
            $table->string('label');
            $table->string('key', 64);
            $table->string('type', 32); // App\Enums\FormFieldType
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->json('style_settings')->nullable();
            $table->json('conditional_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['form_id', 'key']);
            $table->index(['form_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
