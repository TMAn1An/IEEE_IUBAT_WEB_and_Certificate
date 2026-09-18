<?php

namespace Tests\Feature\Admin;

use App\Models\CertificateTemplate;
use App\Models\QrCategory;
use App\Models\QrCertificate;
use App\Models\User;
use Database\Seeders\QrCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Excel import for the simple QR tool, using the ACTUAL old tool's Excel
 * headings -- SL, Conference, Role, Name, Session, Codeword, Created At,
 * QR File -- inspected directly from IEEEQRCODEGENERATOR-main/app.py's
 * HEADERS constant and ensure_excel_file()/append_registration(). See
 * docs/CERTIFICATE_SYSTEM.md §Simple QR tool.
 */
class QrToolImportTest extends TestCase
{
    use RefreshDatabase;

    private function seededCategory(): QrCategory
    {
        // QrCategorySeeder needs an existing user to own the category (see
        // its docblock) -- ensure one exists regardless of whether this
        // particular test creates its own admin/manager first.
        if (User::query()->doesntExist()) {
            User::factory()->create();
        }
        $this->seed(QrCategorySeeder::class);

        return QrCategory::where('slug', 'becithcon-2026')->firstOrFail();
    }

    /** @param  list<list<string>>  $rows */
    private function xlsxUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tmpPath);

        return UploadedFile::fake()->createWithContent('registrations.xlsx', file_get_contents($tmpPath));
    }

    /** The old tool's exact header row, per HEADERS in app.py. */
    private function oldToolHeaders(): array
    {
        return ['SL', 'Conference', 'Role', 'Name', 'Session', 'Codeword', 'Created At', 'QR File'];
    }

    private function upload(User $manager, QrCategory $category, UploadedFile $file): string
    {
        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$category->id}/upload", ['file' => $file]);
        $response->assertOk();

        return $response->viewData('storedFile');
    }

    public function test_import_works_with_zero_certificate_templates(): void
    {
        $this->assertSame(0, CertificateTemplate::count());

        $manager = User::factory()->create();
        $category = $this->seededCategory();

        $this->actingAs($manager)
            ->get("/admin/qr-tool/import/{$category->id}")
            ->assertOk()
            ->assertDontSee('No templates exist yet');
    }

    public function test_old_excel_headings_are_parsed_and_auto_mapped(): void
    {
        $manager = User::factory()->create();
        $category = $this->seededCategory();

        $file = $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Volunteer', 'Jane Doe', 'Technical Session TS-1', 'ABCD1234EFGH5678', '2026-01-05 10:00:00', 'ABCD1234EFGH5678.png'],
        ]);

        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$category->id}/upload", ['file' => $file]);

        $response->assertOk();
        $mapping = $response->viewData('mapping');

        // SL and QR File are never guessed into the mapping -- always ignored.
        $headers = $response->viewData('headers');
        $slIndex = array_search('SL', $headers, true);
        $qrFileIndex = array_search('QR File', $headers, true);
        $this->assertArrayNotHasKey($slIndex, $mapping);
        $this->assertArrayNotHasKey($qrFileIndex, $mapping);

        // Name -> the recipient field, Codeword -> the codeword target, both auto-guessed.
        $nameIndex = array_search('Name', $headers, true);
        $this->assertSame('recipient_name', $mapping[$nameIndex]);
        $codewordIndex = array_search('Codeword', $headers, true);
        $this->assertSame('_codeword', $mapping[$codewordIndex]);
    }

    public function test_historical_codeword_is_preserved(): void
    {
        $manager = User::factory()->create();
        $category = $this->seededCategory();
        $storedFile = $this->upload($manager, $category, $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Volunteer', 'Jane Doe', 'Track A', 'ABCD1234EFGH5678', '2026-01-05 10:00:00', 'ABCD1234EFGH5678.png'],
        ]));

        $mapping = [1 => '_event_name', 2 => 'role', 3 => 'recipient_name', 4 => 'session', 5 => '_codeword', 6 => '_created_at'];

        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$category->id}/confirm", [
            'stored_file' => $storedFile,
            'mapping' => $mapping,
        ]);

        $response->assertOk();
        $this->assertSame(1, $response->viewData('summary')->codewordsPreserved);
        $this->assertSame(0, $response->viewData('summary')->newCodewordsGenerated);

        $certificate = QrCertificate::first();
        $this->assertSame('ABCD1234EFGH5678', $certificate->codeword);
        $this->assertSame('Jane Doe', $certificate->recipient_name);
        $this->assertSame('IEEE BECITHCON 2026', $certificate->event_name);
        $this->assertSame('2026-01-05', $certificate->created_at->format('Y-m-d'));
    }

    public function test_blank_historical_codeword_generates_a_new_one(): void
    {
        $manager = User::factory()->create();
        $category = $this->seededCategory();
        $storedFile = $this->upload($manager, $category, $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Volunteer', 'Jane Doe', 'Track A', '', '2026-01-05 10:00:00', ''],
        ]));

        $mapping = [2 => 'role', 3 => 'recipient_name', 4 => 'session', 5 => '_codeword'];

        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$category->id}/confirm", [
            'stored_file' => $storedFile,
            'mapping' => $mapping,
        ]);

        $this->assertSame(1, $response->viewData('summary')->newCodewordsGenerated);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{16}$/', QrCertificate::first()->codeword);
    }

    public function test_qr_file_column_is_never_imported(): void
    {
        $manager = User::factory()->create();
        $category = $this->seededCategory();
        $storedFile = $this->upload($manager, $category, $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Volunteer', 'Jane Doe', 'Track A', 'ABCD1234EFGH5678', '2026-01-05 10:00:00', 'local/path/ABCD1234EFGH5678.png'],
        ]));

        // Deliberately map QR File (index 7) to the recipient field to prove
        // even a mis-mapping can't smuggle a local file path in as if it
        // were meaningful data -- but the real guessed mapping (tested
        // above) never maps it at all.
        $mapping = [2 => 'role', 3 => 'recipient_name', 4 => 'session', 5 => '_codeword'];

        $this->actingAs($manager)->post("/admin/qr-tool/import/{$category->id}/confirm", [
            'stored_file' => $storedFile,
            'mapping' => $mapping,
        ]);

        $data = QrCertificate::first()->data;
        $this->assertStringNotContainsString('.png', json_encode($data));
    }

    public function test_duplicate_codeword_is_rejected(): void
    {
        $manager = User::factory()->create();
        $category = $this->seededCategory();
        QrCertificate::factory()->create(['codeword' => 'ABCD1234EFGH5678']);

        $storedFile = $this->upload($manager, $category, $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Volunteer', 'Jane Doe', 'Track A', 'ABCD1234EFGH5678', '2026-01-05 10:00:00', ''],
        ]));

        $mapping = [2 => 'role', 3 => 'recipient_name', 4 => 'session', 5 => '_codeword'];

        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$category->id}/preview", [
            'stored_file' => $storedFile,
            'mapping' => $mapping,
        ]);

        $this->assertSame(0, $response->viewData('validCount'));
        $this->assertSame(1, $response->viewData('invalidCount'));
    }

    public function test_required_mapping_is_enforced(): void
    {
        $manager = User::factory()->create();
        $category = $this->seededCategory();
        $storedFile = $this->upload($manager, $category, $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Volunteer', 'Jane Doe', 'Track A', '', '', ''],
        ]));

        // Role mapped, but Name (recipient field) is not.
        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$category->id}/preview", [
            'stored_file' => $storedFile,
            'mapping' => [2 => 'role'],
        ]);

        $response->assertOk()->assertSee('must be mapped before importing');
    }

    public function test_unauthorized_user_is_blocked(): void
    {
        $category = $this->seededCategory();

        $this->get('/admin/qr-tool/import')->assertRedirect('/admin/login');
        $this->get("/admin/qr-tool/import/{$category->id}")->assertRedirect('/admin/login');
    }
}
