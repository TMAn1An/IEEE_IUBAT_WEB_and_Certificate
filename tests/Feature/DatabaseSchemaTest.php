<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 2 required test 7: migrations run successfully. RefreshDatabase
 * already re-runs every migration for each test class in this suite (a
 * failure there fails the whole run), so this makes the check explicit
 * rather than only implicit. Extended in Phase 3 with `is_recipient_name`.
 */
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_2_tables_and_key_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['role', 'is_active']));

        $this->assertTrue(Schema::hasTable('certificate_templates'));
        $this->assertTrue(Schema::hasColumns('certificate_templates', [
            'name', 'slug', 'source_pdf_path', 'page_width', 'page_height', 'status', 'created_by',
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
            'recipient_name', 'data', 'status', 'issued_at', 'revoked_at', 'revocation_reason',
            'reissued_from_id', 'created_by',
        ]));
    }
}
