<?php

namespace Tests\Feature\Admin;

use App\Models\CertificateTemplate;
use App\Models\QrCategory;
use App\Models\QrCertificate;
use App\Models\QrGroup;
use App\Models\User;
use App\Services\QrTool\QrGroupService;
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
 * HEADERS constant and ensure_excel_file()/append_registration(). Import is
 * always INTO a destination QrGroup — see docs/CERTIFICATE_SYSTEM.md
 * §Simple QR tool: group-based import.
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

    private function group(string $role = 'Volunteer', string $eventName = 'IEEE BECITHCON 2026', string $eventType = 'Conference'): QrGroup
    {
        return app(QrGroupService::class)->resolve($eventType, $eventName, $role);
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

    private function upload(User $manager, QrGroup $group, UploadedFile $file): string
    {
        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$group->id}/upload", ['file' => $file]);
        $response->assertOk();

        return $response->viewData('storedFile');
    }

    public function test_import_works_with_zero_certificate_templates(): void
    {
        $this->assertSame(0, CertificateTemplate::count());

        $manager = User::factory()->create();
        $this->seededCategory();
        $group = $this->group();

        $this->actingAs($manager)
            ->get("/admin/qr-tool/import/{$group->id}")
            ->assertOk()
            ->assertDontSee('No templates exist yet');
    }

    public function test_old_excel_headings_are_parsed_and_auto_mapped(): void
    {
        $manager = User::factory()->create();
        $this->seededCategory();
        $group = $this->group();

        $file = $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Volunteer', 'Jane Doe', 'Technical Session TS-1', 'ABCD1234EFGH5678', '2026-01-05 10:00:00', 'ABCD1234EFGH5678.png'],
        ]);

        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$group->id}/upload", ['file' => $file]);

        $response->assertOk();
        $mapping = $response->viewData('mapping');

        // SL and QR File are never guessed into the mapping -- always ignored.
        $headers = $response->viewData('headers');
        $slIndex = array_search('SL', $headers, true);
        $qrFileIndex = array_search('QR File', $headers, true);
        $this->assertArrayNotHasKey($slIndex, $mapping);
        $this->assertArrayNotHasKey($qrFileIndex, $mapping);

        // Name -> the recipient field, Codeword -> the codeword target,
        // Conference/Role -> validate-only targets, all auto-guessed.
        $nameIndex = array_search('Name', $headers, true);
        $this->assertSame('recipient_name', $mapping[$nameIndex]);
        $codewordIndex = array_search('Codeword', $headers, true);
        $this->assertSame('_codeword', $mapping[$codewordIndex]);
        $conferenceIndex = array_search('Conference', $headers, true);
        $this->assertSame('_conference_validate', $mapping[$conferenceIndex]);
        $roleIndex = array_search('Role', $headers, true);
        $this->assertSame('_role_validate', $mapping[$roleIndex]);
    }

    public function test_historical_codeword_is_preserved(): void
    {
        $manager = User::factory()->create();
        $this->seededCategory();
        $group = $this->group();
        $storedFile = $this->upload($manager, $group, $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Volunteer', 'Jane Doe', 'Track A', 'ABCD1234EFGH5678', '2026-01-05 10:00:00', 'ABCD1234EFGH5678.png'],
        ]));

        $mapping = [1 => '_conference_validate', 2 => '_role_validate', 3 => 'recipient_name', 4 => 'session', 5 => '_codeword', 6 => '_created_at'];

        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$group->id}/confirm", [
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
        $this->assertSame($group->id, $certificate->qr_group_id);
        $this->assertSame('Volunteer', $certificate->data['role']);
        $this->assertSame('2026-01-05', $certificate->created_at->format('Y-m-d'));
    }

    public function test_blank_historical_codeword_generates_a_new_one(): void
    {
        $manager = User::factory()->create();
        $this->seededCategory();
        $group = $this->group();
        $storedFile = $this->upload($manager, $group, $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Volunteer', 'Jane Doe', 'Track A', '', '2026-01-05 10:00:00', ''],
        ]));

        $mapping = [2 => '_role_validate', 3 => 'recipient_name', 4 => 'session', 5 => '_codeword'];

        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$group->id}/confirm", [
            'stored_file' => $storedFile,
            'mapping' => $mapping,
        ]);

        $this->assertSame(1, $response->viewData('summary')->newCodewordsGenerated);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{16}$/', QrCertificate::first()->codeword);
    }

    public function test_qr_file_column_is_never_imported(): void
    {
        $manager = User::factory()->create();
        $this->seededCategory();
        $group = $this->group();
        $storedFile = $this->upload($manager, $group, $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Volunteer', 'Jane Doe', 'Track A', 'ABCD1234EFGH5678', '2026-01-05 10:00:00', 'local/path/ABCD1234EFGH5678.png'],
        ]));

        // Deliberately map QR File (index 7) to the recipient field to prove
        // even a mis-mapping can't smuggle a local file path in as if it
        // were meaningful data -- but the real guessed mapping (tested
        // above) never maps it at all.
        $mapping = [2 => '_role_validate', 3 => 'recipient_name', 4 => 'session', 5 => '_codeword'];

        $this->actingAs($manager)->post("/admin/qr-tool/import/{$group->id}/confirm", [
            'stored_file' => $storedFile,
            'mapping' => $mapping,
        ]);

        $data = QrCertificate::first()->data;
        $this->assertStringNotContainsString('.png', json_encode($data));
    }

    public function test_duplicate_codeword_is_rejected(): void
    {
        $manager = User::factory()->create();
        $this->seededCategory();
        $group = $this->group();
        QrCertificate::factory()->create(['codeword' => 'ABCD1234EFGH5678']);

        $storedFile = $this->upload($manager, $group, $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Volunteer', 'Jane Doe', 'Track A', 'ABCD1234EFGH5678', '2026-01-05 10:00:00', ''],
        ]));

        $mapping = [2 => '_role_validate', 3 => 'recipient_name', 4 => 'session', 5 => '_codeword'];

        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$group->id}/preview", [
            'stored_file' => $storedFile,
            'mapping' => $mapping,
        ]);

        $this->assertSame(0, $response->viewData('validCount'));
        $this->assertSame(1, $response->viewData('invalidCount'));
    }

    public function test_required_mapping_is_enforced(): void
    {
        $manager = User::factory()->create();
        $this->seededCategory();
        $group = $this->group();
        $storedFile = $this->upload($manager, $group, $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Volunteer', 'Jane Doe', 'Track A', '', '', ''],
        ]));

        // Role validate-only mapped, but Name (recipient field) is not.
        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$group->id}/preview", [
            'stored_file' => $storedFile,
            'mapping' => [2 => '_role_validate'],
        ]);

        $response->assertOk()->assertSee('must be mapped before importing');
    }

    public function test_row_with_mismatched_role_is_rejected_as_a_group_consistency_error(): void
    {
        $manager = User::factory()->create();
        $this->seededCategory();
        $group = $this->group(role: 'Session Chair');

        $storedFile = $this->upload($manager, $group, $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Keynote Speaker', 'Jane Doe', 'Track A', '', '', ''],
        ]));

        $mapping = [2 => '_role_validate', 3 => 'recipient_name'];

        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$group->id}/preview", [
            'stored_file' => $storedFile,
            'mapping' => $mapping,
        ]);

        $response->assertOk()
            ->assertSee('does not match destination group')
            ->assertSee('Keynote Speaker');
        $this->assertSame(0, $response->viewData('validCount'));
    }

    public function test_row_matching_an_existing_group_record_is_flagged_as_a_duplicate(): void
    {
        $manager = User::factory()->create();
        $this->seededCategory();
        $group = $this->group();
        QrCertificate::factory()->create([
            'qr_group_id' => $group->id,
            'recipient_name' => 'Jane Doe',
            'data' => ['recipient_name' => 'Jane Doe', 'role' => 'Volunteer', 'session' => 'Track A'],
        ]);

        $storedFile = $this->upload($manager, $group, $this->xlsxUpload([
            $this->oldToolHeaders(),
            [1, 'IEEE BECITHCON 2026', 'Volunteer', 'Jane Doe', 'Track A', '', '', ''],
        ]));

        $mapping = [3 => 'recipient_name', 4 => 'session'];

        $response = $this->actingAs($manager)->post("/admin/qr-tool/import/{$group->id}/preview", [
            'stored_file' => $storedFile,
            'mapping' => $mapping,
        ]);

        $response->assertOk();
        $this->assertSame(0, $response->viewData('validCount'));
        $this->assertSame(1, $response->viewData('duplicateCount'));
    }

    public function test_choose_group_page_lists_groups_and_offers_create_group_form(): void
    {
        $manager = User::factory()->create();
        $this->seededCategory();
        $group = $this->group();

        $this->actingAs($manager)->get('/admin/qr-tool/import')
            ->assertOk()
            ->assertSee('IEEE BECITHCON 2026')
            ->assertSee('Volunteer')
            ->assertSee('Create a new group');

        $response = $this->actingAs($manager)->post('/admin/qr-tool/import/create-group', [
            'event_type' => 'Workshop', 'event_name' => 'IEEE Winter School 2026', 'role' => 'Volunteer',
        ]);

        $newGroup = QrGroup::where('event_type', 'Workshop')->firstOrFail();
        $response->assertRedirect("/admin/qr-tool/import/{$newGroup->id}");
        $this->assertNotSame($group->id, $newGroup->id);
    }

    public function test_unauthorized_user_is_blocked(): void
    {
        $this->seededCategory();
        $group = $this->group();

        $this->get('/admin/qr-tool/import')->assertRedirect('/admin/login');
        $this->get("/admin/qr-tool/import/{$group->id}")->assertRedirect('/admin/login');
    }
}
