<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 2 required test 7: migrations run successfully. RefreshDatabase
 * already re-runs every migration for each test class in this suite (a
 * failure there fails the whole run), so this makes the check explicit
 * rather than only implicit. Extended in Phase 3 with `is_recipient_name`,
 * Phase 4 with background metadata + system-element layout columns, Phase 5
 * with generated-PDF/snapshot columns and the certificate number counter,
 * and with the simple QR tool's own independent tables (qr_categories,
 * qr_category_fields, qr_certificates — see docs/CERTIFICATE_SYSTEM.md
 * §Simple QR tool for why these are separate from certificate_templates).
 */
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_2_tables_and_key_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['role', 'is_active']));

        $this->assertTrue(Schema::hasTable('certificate_templates'));
        $this->assertTrue(Schema::hasColumns('certificate_templates', [
            'name', 'slug', 'source_pdf_path', 'original_filename', 'file_mime', 'file_size',
            'page_width', 'page_height', 'certificate_number_layout', 'qr_code_layout', 'status', 'created_by',
        ]));

        $this->assertTrue(Schema::hasTable('template_fields'));
        $this->assertTrue(Schema::hasColumns('template_fields', [
            'certificate_template_id', 'label', 'field_key', 'field_type', 'is_required',
            'show_on_verification', 'is_recipient_name', 'verification_label', 'options', 'position',
            'style', 'sort_order',
        ]));

        $this->assertTrue(Schema::hasTable('certificate_batches'));
        $this->assertTrue(Schema::hasColumns('certificate_batches', [
            'certificate_template_id', 'name', 'status', 'total_rows', 'successful_rows', 'failed_rows', 'created_by',
        ]));

        $this->assertTrue(Schema::hasTable('certificates'));
        $this->assertTrue(Schema::hasColumns('certificates', [
            'certificate_template_id', 'certificate_batch_id', 'certificate_number', 'codeword',
            'recipient_name', 'data', 'pdf_path', 'template_snapshot', 'layout_snapshot', 'status',
            'issued_at', 'revoked_at', 'revocation_reason', 'reissued_from_id', 'created_by',
        ]));

        $this->assertTrue(Schema::hasTable('certificate_number_counters'));
        $this->assertTrue(Schema::hasColumns('certificate_number_counters', ['year', 'next_sequence']));

        $this->assertTrue(Schema::hasTable('qr_categories'));
        $this->assertTrue(Schema::hasColumns('qr_categories', [
            'name', 'slug', 'event_name', 'description', 'is_active', 'created_by',
        ]));
        // No column on qr_categories may reference certificate_templates --
        // this table is deliberately fully independent of the advanced
        // system (see docs/CERTIFICATE_SYSTEM.md §Simple QR tool).
        $this->assertFalse(Schema::hasColumn('qr_categories', 'certificate_template_id'));

        $this->assertTrue(Schema::hasTable('qr_category_fields'));
        $this->assertTrue(Schema::hasColumns('qr_category_fields', [
            'qr_category_id', 'label', 'key', 'type', 'required', 'options',
            'sort_order', 'is_recipient_name', 'show_on_verification',
        ]));

        $this->assertTrue(Schema::hasTable('qr_certificates'));
        $this->assertTrue(Schema::hasColumns('qr_certificates', [
            'qr_category_id', 'recipient_name', 'event_name', 'data', 'codeword', 'status', 'created_by',
        ]));
        $this->assertFalse(Schema::hasColumn('qr_certificates', 'certificate_template_id'));
        $this->assertFalse(Schema::hasColumn('qr_certificates', 'pdf_path'));
    }
}
