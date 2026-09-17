<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // The uploaded Canva-exported PDF's path on the `local` (private)
            // disk. Upload handling itself is Phase 4 — this column exists
            // now so the model/schema are ready for it.
            $table->string('source_pdf_path')->nullable();
            $table->decimal('page_width', 8, 2)->nullable();
            $table->decimal('page_height', 8, 2)->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
    }
};
