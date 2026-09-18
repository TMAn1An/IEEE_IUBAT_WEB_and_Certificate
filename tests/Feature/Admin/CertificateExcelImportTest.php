<?php

namespace Tests\Feature\Admin;

use App\Enums\CertificateTemplateStatus;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\TemplateField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Phase 6 required tests (see docs/CHANGELOG.md) for the historical Excel
 * import workflow — App\Http\Controllers\Admin\CertificateImportController
 * + App\Services\Certificates\Import\*.
 */
class CertificateExcelImportTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<list<string>>  $rows  First row is the header row. */
    private function xlsxUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tmpPath);

        return UploadedFile::fake()->createWithContent('import.xlsx', file_get_contents($tmpPath));
    }

    private function templateWithFields(User $creator): CertificateTemplate
    {
        $template = CertificateTemplate::factory()->for($creator, 'creator')->create([
            'status' => CertificateTemplateStatus::Active,
        ]);

        TemplateField::factory()->for($template, 'template')->create([
            'field_key' => 'recipient_name',
            'label' => 'Name',
            'field_type' => 'text',
            'is_required' => true,
            'is_recipient_name' => true,
        ]);

        TemplateField::factory()->for($template, 'template')->create([
            'field_key' => 'track',
            'label' => 'Track',
            'field_type' => 'dropdown',
            'is_required' => true,
            'options' => ['AI', 'Networking'],
        ]);

        return $template->fresh(['fields']);
    }

    /** Uploads and returns the stored-file UUID via the mapping view's data. */
    private function upload(User $manager, CertificateTemplate $template, UploadedFile $file): string
    {
        $response = $this->actingAs($manager)->post("/admin/certificates/import/{$template->id}/upload", ['file' => $file]);
        $response->assertOk();

        return $response->viewData('storedFile');
    }

    public function test_authorized_manager_can_open_import_pages(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);

        $this->actingAs($manager)->get('/admin/certificates/import')->assertOk();
        $this->actingAs($manager)->get("/admin/certificates/import/{$template->id}")->assertOk();
    }

    public function test_unauthorized_user_is_blocked(): void
    {
        $template = $this->templateWithFields(User::factory()->create());

        $this->get('/admin/certificates/import')->assertRedirect('/admin/login');
        $this->get("/admin/certificates/import/{$template->id}")->assertRedirect('/admin/login');
    }

    public function test_excel_upload_is_accepted_and_headers_are_parsed(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $file = $this->xlsxUpload([
            ['Name', 'Track'],
            ['Jane Doe', 'AI'],
        ]);

        $response = $this->actingAs($manager)->post("/admin/certificates/import/{$template->id}/upload", ['file' => $file]);

        $response->assertOk()->assertSee('Name')->assertSee('Track');
        $this->assertSame(['Name', 'Track'], $response->viewData('headers'));
    }

    public function test_non_excel_file_is_rejected(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $this->actingAs($manager)
            ->post("/admin/certificates/import/{$template->id}/upload", ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_required_mapping_is_enforced_before_preview(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $storedFile = $this->upload($manager, $template, $this->xlsxUpload([
            ['Name', 'Track'],
            ['Jane Doe', 'AI'],
        ]));

        // Track mapped, but Name (the recipient field) is not.
        $response = $this->actingAs($manager)->post("/admin/certificates/import/{$template->id}/preview", [
            'stored_file' => $storedFile,
            'mapping' => [1 => 'track'],
        ]);

        $response->assertOk()->assertSee('must be mapped before importing');
    }

    public function test_column_mapping_and_preview_report_correct_counts(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $storedFile = $this->upload($manager, $template, $this->xlsxUpload([
            ['Name', 'Track'],
            ['Jane Doe', 'AI'],
            ['', 'AI'],                 // invalid: missing name
            ['John Smith', 'Speaker123'], // invalid: bad dropdown value
        ]));

        $response = $this->actingAs($manager)->post("/admin/certificates/import/{$template->id}/preview", [
            'stored_file' => $storedFile,
            'mapping' => [0 => 'recipient_name', 1 => 'track'],
        ]);

        $response->assertOk();
        $this->assertSame(3, $response->viewData('totalRows'));
        $this->assertSame(1, $response->viewData('validCount'));
        $this->assertSame(2, $response->viewData('invalidCount'));
    }

    public function test_invalid_dropdown_row_is_rejected(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $storedFile = $this->upload($manager, $template, $this->xlsxUpload([
            ['Name', 'Track'],
            ['Jane Doe', 'Not A Track'],
        ]));

        $response = $this->actingAs($manager)->post("/admin/certificates/import/{$template->id}/preview", [
            'stored_file' => $storedFile,
            'mapping' => [0 => 'recipient_name', 1 => 'track'],
        ]);

        $this->assertSame(0, $response->viewData('validCount'));
        $this->assertSame(1, $response->viewData('invalidCount'));
    }

    public function test_import_confirmation_writes_database_rows(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $storedFile = $this->upload($manager, $template, $this->xlsxUpload([
            ['Name', 'Track'],
            ['Jane Doe', 'AI'],
            ['John Smith', 'Networking'],
        ]));
        $mapping = [0 => 'recipient_name', 1 => 'track'];

        $response = $this->actingAs($manager)->post("/admin/certificates/import/{$template->id}/confirm", [
            'stored_file' => $storedFile,
            'mapping' => $mapping,
        ]);

        $response->assertOk();
        $this->assertSame(2, $response->viewData('summary')->imported);
        $this->assertDatabaseCount('certificates', 2);

        $jane = Certificate::where('recipient_name', 'Jane Doe')->first();
        $this->assertSame('AI', $jane->data['track']);
        $this->assertNull($jane->pdf_path);
    }

    public function test_existing_codeword_is_preserved_when_mapped_and_valid(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $storedFile = $this->upload($manager, $template, $this->xlsxUpload([
            ['Name', 'Track', 'Codeword'],
            ['Jane Doe', 'AI', 'legacycode123'],
        ]));

        $response = $this->actingAs($manager)->post("/admin/certificates/import/{$template->id}/confirm", [
            'stored_file' => $storedFile,
            'mapping' => [0 => 'recipient_name', 1 => 'track', 2 => '_codeword'],
        ]);

        $this->assertSame(1, $response->viewData('summary')->codewordsPreserved);
        $this->assertSame(0, $response->viewData('summary')->newCodewordsGenerated);
        $this->assertSame('legacycode123', Certificate::first()->codeword);
    }

    public function test_duplicate_codeword_against_existing_database_row_is_rejected(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        Certificate::factory()->create(['codeword' => 'legacycode123']);

        $storedFile = $this->upload($manager, $template, $this->xlsxUpload([
            ['Name', 'Track', 'Codeword'],
            ['Jane Doe', 'AI', 'legacycode123'],
        ]));

        $response = $this->actingAs($manager)->post("/admin/certificates/import/{$template->id}/preview", [
            'stored_file' => $storedFile,
            'mapping' => [0 => 'recipient_name', 1 => 'track', 2 => '_codeword'],
        ]);

        $this->assertSame(0, $response->viewData('validCount'));
        $this->assertSame(1, $response->viewData('invalidCount'));
    }

    public function test_duplicate_codeword_within_the_same_file_is_rejected(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $storedFile = $this->upload($manager, $template, $this->xlsxUpload([
            ['Name', 'Track', 'Codeword'],
            ['Jane Doe', 'AI', 'samecode123'],
            ['John Smith', 'AI', 'samecode123'],
        ]));

        $response = $this->actingAs($manager)->post("/admin/certificates/import/{$template->id}/preview", [
            'stored_file' => $storedFile,
            'mapping' => [0 => 'recipient_name', 1 => 'track', 2 => '_codeword'],
        ]);

        $this->assertSame(1, $response->viewData('validCount'));
        $this->assertSame(1, $response->viewData('invalidCount'));
    }

    public function test_missing_codeword_generates_a_new_one(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $storedFile = $this->upload($manager, $template, $this->xlsxUpload([
            ['Name', 'Track', 'Codeword'],
            ['Jane Doe', 'AI', ''],
        ]));

        $response = $this->actingAs($manager)->post("/admin/certificates/import/{$template->id}/confirm", [
            'stored_file' => $storedFile,
            'mapping' => [0 => 'recipient_name', 1 => 'track', 2 => '_codeword'],
        ]);

        $this->assertSame(1, $response->viewData('summary')->newCodewordsGenerated);
        $this->assertSame(64, strlen(Certificate::first()->codeword));
    }

    public function test_existing_certificate_number_is_preserved_when_valid(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $storedFile = $this->upload($manager, $template, $this->xlsxUpload([
            ['Name', 'Track', 'Cert No'],
            ['Jane Doe', 'AI', 'LEGACY-2020-001'],
        ]));

        $response = $this->actingAs($manager)->post("/admin/certificates/import/{$template->id}/confirm", [
            'stored_file' => $storedFile,
            'mapping' => [0 => 'recipient_name', 1 => 'track', 2 => '_certificate_number'],
        ]);

        $this->assertSame(1, $response->viewData('summary')->certificateNumbersPreserved);
        $this->assertSame('LEGACY-2020-001', Certificate::first()->certificate_number);
    }

    public function test_duplicate_certificate_number_is_rejected(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        Certificate::factory()->create(['certificate_number' => 'LEGACY-2020-001']);

        $storedFile = $this->upload($manager, $template, $this->xlsxUpload([
            ['Name', 'Track', 'Cert No'],
            ['Jane Doe', 'AI', 'LEGACY-2020-001'],
        ]));

        $response = $this->actingAs($manager)->post("/admin/certificates/import/{$template->id}/preview", [
            'stored_file' => $storedFile,
            'mapping' => [0 => 'recipient_name', 1 => 'track', 2 => '_certificate_number'],
        ]);

        $this->assertSame(0, $response->viewData('validCount'));
        $this->assertSame(1, $response->viewData('invalidCount'));
    }

    public function test_previously_issued_phase_5_certificates_remain_intact(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $existing = Certificate::factory()->create([
            'certificate_template_id' => $template->id,
            'certificate_number' => 'IEEE-IUBAT-2026-999999',
            'recipient_name' => 'Untouched Person',
        ]);

        $storedFile = $this->upload($manager, $template, $this->xlsxUpload([
            ['Name', 'Track'],
            ['Jane Doe', 'AI'],
        ]));

        $this->actingAs($manager)->post("/admin/certificates/import/{$template->id}/confirm", [
            'stored_file' => $storedFile,
            'mapping' => [0 => 'recipient_name', 1 => 'track'],
        ]);

        $existing->refresh();
        $this->assertSame('Untouched Person', $existing->recipient_name);
        $this->assertSame('IEEE-IUBAT-2026-999999', $existing->certificate_number);
        $this->assertDatabaseCount('certificates', 2);
    }
}
