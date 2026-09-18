<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reproduces the old tool's automatic per-combination Excel files (e.g.
 * `Conference_IEEE_BECITHCON_2026_Role_Session_Chair.xlsx`) as a database
 * concept: a group is the auto-created Event Type + Event Name + Role
 * combination a QR record belongs to. NOT the same thing as the persisted
 * dropdown OPTION lists (`qr_conference_types`/`qr_conference_options`,
 * role options on `qr_category_fields`) — those are selectable values;
 * a group is an automatically created combination of values. See
 * docs/CERTIFICATE_SYSTEM.md §Simple QR tool: automatic grouping.
 *
 * Deliberately has no foreign key to `qr_categories` or `CertificateTemplate`
 * — a group is an orthogonal concept, not a schema/field-definition owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_groups', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('event_name');
            $table->string('role');
            // Normalized (trimmed, whitespace-collapsed, lowercased)
            // "event_type\x1Fevent_name\x1Frole" — the actual uniqueness
            // constraint. Kept as a real column (not a generated one) so
            // find-or-create is a single indexed lookup, not a full scan.
            $table->string('group_key')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_groups');
    }
};
