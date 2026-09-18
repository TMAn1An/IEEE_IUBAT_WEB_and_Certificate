<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The name options nested under a conference type — see qr_conference_types' migration docblock. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_conference_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_conference_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['qr_conference_type_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_conference_options');
    }
};
