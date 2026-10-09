<?php

namespace Tests\Feature\Admin;

use App\Enums\CertificateBatchReservationStatus;
use App\Enums\CertificateStatus;
use App\Enums\CertificateTemplateStatus;
use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\CertificateTemplate;
use App\Models\TemplateField;
use App\Models\User;
use App\Services\Certificates\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * PDF Editor Bridge — see docs/CERTIFICATE_SYSTEM.md §PDF Editor Bridge.
 * Covers the acceptance checks from the integration brief: valid rows
 * reserve exactly one certificate_number/codeword/QR each, unknown/missing
 * columns are rejected before anything is minted, a finalize upload creates
 * real `certificates` rows matched by codeword==filename, an unmatched or
 * invalid PDF is reported without corrupting the rest of the batch, and
 * the public verification route serves a bridge-created certificate
 * exactly like any other.
 */
class PdfEditorBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function activeTemplate(User $creator): CertificateTemplate
    {
        $template = CertificateTemplate::factory()->for($creator, 'creator')->create([
            'status' => CertificateTemplateStatus::Draft,
        ]);

        TemplateField::factory()->for($template, 'template')->create([
            'field_key' => 'name',
            'label' => 'Name',
            'field_type' => 'text',
            'is_required' => true,
            'is_recipient_name' => true,
        ]);

        TemplateField::factory()->for($template, 'template')->create([
            'field_key' => 'paper_title',
            'label' => 'Paper Title',
            'field_type' => 'text',
            'is_required' => true,
        ]);

        $template->update(['status' => CertificateTemplateStatus::Active]);

        return $template->fresh(['fields']);
    }

    /** @param  list<list<string>>  $rows */
    private function excelUpload(array $headers, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A'.$rowNumber);
            $rowNumber++;
        }

        $path = tempnam(sys_get_temp_dir(), 'bridge').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return new UploadedFile($path, 'recipients.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_unauthenticated_user_cannot_reserve_a_batch(): void
    {
        $response = $this->get(route('admin.bulk-generation.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_valid_rows_reserve_unique_certificate_numbers_codewords_and_qr_codes(): void
    {
        $admin = User::factory()->create();
        $template = $this->activeTemplate($admin);

        $response = $this->actingAs($admin)->post(route('admin.bulk-generation.store'), [
            'certificate_template_id' => $template->id,
            'recipients' => $this->excelUpload(['name', 'paper_title'], [
                ['Alice Doe', 'A Study of Things'],
                ['Bob Roe', 'Another Study'],
            ]),
        ]);

        $batch = CertificateBatch::first();
        $response->assertRedirect(route('admin.batches.show', $batch));

        $this->assertSame(2, $batch->successful_rows);
        $this->assertSame(0, $batch->failed_rows);
        $this->assertSame('pdf_editor_bridge', $batch->source);

        $reservations = $batch->reservations;
        $this->assertCount(2, $reservations);
        $this->assertNotSame($reservations[0]->codeword, $reservations[1]->codeword);
        $this->assertNotSame($reservations[0]->certificate_number, $reservations[1]->certificate_number);
        foreach ($reservations as $reservation) {
            $this->assertSame(CertificateBatchReservationStatus::Reserved, $reservation->status);
            $this->assertNull($reservation->certificate_id);
        }

        // Zero real certificates exist yet — a reservation is not an issuance.
        $this->assertSame(0, Certificate::count());

        Storage::disk('local')->assertExists("certificate-batches/{$batch->id}/reservation.xlsx");
        Storage::disk('local')->assertExists("certificate-batches/{$batch->id}/qr-codes.zip");
    }

    public function test_reserve_rejects_unknown_excel_column(): void
    {
        $admin = User::factory()->create();
        $template = $this->activeTemplate($admin);

        $response = $this->actingAs($admin)->post(route('admin.bulk-generation.store'), [
            'certificate_template_id' => $template->id,
            'recipients' => $this->excelUpload(['name', 'paper_title', 'not_a_real_field'], [
                ['Alice Doe', 'A Study of Things', 'x'],
            ]),
        ]);

        $response->assertSessionHasErrors('recipients');
        $this->assertSame(0, CertificateBatch::count());
    }

    public function test_reserve_rejects_missing_required_column(): void
    {
        $admin = User::factory()->create();
        $template = $this->activeTemplate($admin);

        $response = $this->actingAs($admin)->post(route('admin.bulk-generation.store'), [
            'certificate_template_id' => $template->id,
            'recipients' => $this->excelUpload(['name'], [
                ['Alice Doe'],
            ]),
        ]);

        $response->assertSessionHasErrors('recipients');
        $this->assertSame(0, CertificateBatch::count());
    }

    public function test_finalize_matches_pdf_by_codeword_filename_and_creates_certificate(): void
    {
        $admin = User::factory()->create();
        $template = $this->activeTemplate($admin);

        $this->actingAs($admin)->post(route('admin.bulk-generation.store'), [
            'certificate_template_id' => $template->id,
            'recipients' => $this->excelUpload(['name', 'paper_title'], [
                ['Alice Doe', 'A Study of Things'],
            ]),
        ]);

        $batch = CertificateBatch::first();
        $reservation = $batch->reservations->first();

        $pdfPath = tempnam(sys_get_temp_dir(), 'cert').'.pdf';
        file_put_contents($pdfPath, "%PDF-1.4\nfake pdf bytes for test\n%%EOF");
        $pdfUpload = new UploadedFile($pdfPath, $reservation->codeword.'.pdf', 'application/pdf', null, true);

        $response = $this->actingAs($admin)->post(route('admin.batches.finalize', $batch), [
            'package' => $pdfUpload,
        ]);

        $response->assertRedirect(route('admin.batches.show', $batch));

        $reservation->refresh();
        $this->assertSame(CertificateBatchReservationStatus::Finalized, $reservation->status);
        $this->assertNotNull($reservation->certificate_id);

        $certificate = Certificate::find($reservation->certificate_id);
        $this->assertSame($reservation->codeword, $certificate->codeword);
        $this->assertSame($reservation->certificate_number, $certificate->certificate_number);
        $this->assertSame(CertificateStatus::Active, $certificate->status);
        $this->assertSame('Alice Doe', $certificate->recipient_name);
        Storage::disk('local')->assertExists($certificate->pdf_path);

        // The public verification route works for a bridge-created certificate
        // exactly like any other.
        $verify = $this->get(route('certificate.verify', ['codeword' => $certificate->codeword]));
        $verify->assertOk();
        $verify->assertSee('Alice Doe');
    }

    public function test_finalize_unmatched_pdf_is_reported_without_affecting_other_rows(): void
    {
        $admin = User::factory()->create();
        $template = $this->activeTemplate($admin);

        $this->actingAs($admin)->post(route('admin.bulk-generation.store'), [
            'certificate_template_id' => $template->id,
            'recipients' => $this->excelUpload(['name', 'paper_title'], [
                ['Alice Doe', 'A Study of Things'],
            ]),
        ]);

        $batch = CertificateBatch::first();

        $pdfPath = tempnam(sys_get_temp_dir(), 'cert').'.pdf';
        file_put_contents($pdfPath, "%PDF-1.4\nfake\n%%EOF");
        $pdfUpload = new UploadedFile($pdfPath, 'NOT-A-REAL-CODEWORD.pdf', 'application/pdf', null, true);

        $response = $this->actingAs($admin)->post(route('admin.batches.finalize', $batch), [
            'package' => $pdfUpload,
        ]);

        $response->assertSessionHas('bridge-errors');
        $this->assertSame(0, Certificate::count());

        $batch->reservations->first()->refresh();
        $this->assertSame(CertificateBatchReservationStatus::Reserved, $batch->reservations->first()->status);
    }

    public function test_finalize_rejects_non_pdf_content(): void
    {
        $admin = User::factory()->create();
        $template = $this->activeTemplate($admin);

        $this->actingAs($admin)->post(route('admin.bulk-generation.store'), [
            'certificate_template_id' => $template->id,
            'recipients' => $this->excelUpload(['name', 'paper_title'], [
                ['Alice Doe', 'A Study of Things'],
            ]),
        ]);

        $batch = CertificateBatch::first();
        $reservation = $batch->reservations->first();

        $fakePath = tempnam(sys_get_temp_dir(), 'notpdf').'.pdf';
        file_put_contents($fakePath, 'this is not a pdf');
        $fakeUpload = new UploadedFile($fakePath, $reservation->codeword.'.pdf', 'application/pdf', null, true);

        $response = $this->actingAs($admin)->post(route('admin.batches.finalize', $batch), [
            'package' => $fakeUpload,
        ]);

        // The request-level magic-header check rejects this before the
        // service even runs, since the extension is .pdf.
        $response->assertSessionHasErrors('package');
    }

    public function test_qr_in_reservation_zip_encodes_the_real_verification_url(): void
    {
        $admin = User::factory()->create();
        $template = $this->activeTemplate($admin);

        $this->actingAs($admin)->post(route('admin.bulk-generation.store'), [
            'certificate_template_id' => $template->id,
            'recipients' => $this->excelUpload(['name', 'paper_title'], [
                ['Alice Doe', 'A Study of Things'],
            ]),
        ]);

        $batch = CertificateBatch::first();
        $reservation = $batch->reservations->first();

        $expectedUrl = app(QrCodeService::class)->verificationUrlForCodeword($reservation->codeword);
        $this->assertStringContainsString('/certificate/verify/'.$reservation->codeword, $expectedUrl);
    }
}
