<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** See qr_groups' migration docblock. Nullable: a group is always resolved by the application before a record is created, but the column itself doesn't need to forbid null at the DB level. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_certificates', function (Blueprint $table) {
            $table->foreignId('qr_group_id')->nullable()->after('qr_category_id')->constrained('qr_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('qr_certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qr_group_id');
        });
    }
};
