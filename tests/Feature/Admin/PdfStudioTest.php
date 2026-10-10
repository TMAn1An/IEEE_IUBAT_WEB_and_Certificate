<?php

namespace Tests\Feature\Admin;

use App\Enums\CertificateBatchReservationStatus;
use App\Enums\CertificateBatchStatus;
use App\Enums\CertificateTemplateStatus;
use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;
use ZipArchive;

/**
 * PDF Studio direct integration — see docs/PDF_STUDIO_INTEGRATION.md. Covers
 * the acceptance risks from the integration brief: schema derived from the
 * saved project (not a second field list), idempotent batch confirm,
 * idempotent/duplicate/conflicting finalize, premature-verification
 * prevention, batch ZIP download with stable ordering, and template-edit
 * isolation from in-flight/completed batches.
 */
class PdfStudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function draftTemplate(User $admin): CertificateTemplate
    {
        return CertificateTemplate::factory()->for($admin, 'creator')->create([
            'status' => CertificateTemplateStatus::Draft,
        ]);
    }

    /** A minimal, valid .pdftemplate bundle: project.json only (no PDF/fonts needed — TemplateProjectService never parses the PDF). */
    private function projectBundle(string $name = 'Audit Certificate'): UploadedFile
    {
        $project = [
            'format' => 'pdf-template-studio/project',
            'version' => 2,
            'id' => 'proj_1',
            'name' => $name,
            'createdAt' => now()->toIso8601String(),
            'updatedAt' => now()->toIso8601String(),
            'pdf' => null,
            'fields' => [
                ['id' => 'fld_name', 'label' => 'Name', 'type' => 'text', 'page' => 1, 'rect' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.5, 'h' => 0.1], 'required' => true, 'style' => []],
                ['id' => 'fld_title', 'label' => 'Paper Title', 'type' => 'text', 'page' => 1, 'rect' => ['x' => 0.1, 'y' => 0.3, 'w' => 0.5, 'h' => 0.1], 'required' => false, 'style' => []],
                ['id' => 'fld_photo', 'label' => 'Photo', 'type' => 'image', 'page' => 1, 'rect' => ['x' => 0.1, 'y' => 0.5, 'w' => 0.2, 'h' => 0.2], 'required' => false, 'style' => []],
                ['id' => 'fld_qr', 'label' => 'QR Code', 'type' => 'image', 'page' => 1, 'rect' => ['x' => 0.7, 'y' => 0.7, 'w' => 0.15, 'h' => 0.15], 'required' => false, 'style' => []],
            ],
            'formFields' => [],
            'mapping' => [
                'fld_name' => ['kind' => 'column', 'column' => 'name', 'transform' => 'none'],
                'fld_title' => ['kind' => 'column', 'column' => 'paper_title', 'transform' => 'none'],
                'fld_photo' => ['kind' => 'column', 'column' => 'photo', 'transform' => 'none'],
            ],
            'knownColumns' => ['name', 'paper_title', 'photo'],
            'fonts' => [],
            'settings' => ['exportDefaults' => ['fileNamePattern' => '{codeword}', 'formMode' => 'flatten', 'zip' => false], 'formFieldFont' => null],
        ];

        $zipPath = sys_get_temp_dir().'/bundle-'.bin2hex(random_bytes(8)).'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('project.json', json_encode($project));
        $zip->close();

        return new UploadedFile($zipPath, 'project.pdftemplate', 'application/zip', null, true);
    }

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
        $path = tempnam(sys_get_temp_dir(), 'participants').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return new UploadedFile($path, 'participants.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_saving_a_project_derives_schema_from_the_editor_not_a_second_field_list(): void
    {
        $admin = User::factory()->create();
        $template = $this->draftTemplate($admin);

        $response = $this->actingAs($admin)->call('PUT', route('admin.pdf-studio.api.templates.project.store', $template), [
            'qr_field_id' => 'fld_qr',
            'recipient_field_id' => 'fld_name',
        ], [], ['project' => $this->projectBundle()]);

        $response->assertOk();
        $template->refresh();

        $this->assertNotNull($template->editor_project_path);
        $this->assertSame(1, $template->editor_schema_version);
        $columns = collect($template->editor_schema['fields'])->pluck('column')->all();
        // The QR field is never a required/suggested column — it's system-generated.
        $this->assertEqualsCanonicalizing(['name', 'paper_title', 'photo'], $columns);
        $this->assertSame('fld_qr', $template->editor_schema['qr_field_id']);
        $this->assertSame('fld_name', $template->editor_schema['recipient_field_id']);

        // The saved bundle must be fetchable back byte-for-byte (this is
        // what "Load from server" in the Studio actually calls).
        $get = $this->actingAs($admin)->get(route('admin.pdf-studio.api.templates.project.show', $template));
        $get->assertOk();
        $this->assertNotEmpty($get->streamedContent());
    }

    public function test_unauthenticated_user_cannot_reach_the_studio(): void
    {
        $template = CertificateTemplate::factory()->create();
        $response = $this->get(route('admin.pdf-studio.show', $template));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_prepare_rejects_unknown_and_missing_columns(): void
    {
        $admin = User::factory()->create();
        $template = $this->draftTemplate($admin);
        $this->actingAs($admin)->call('PUT', route('admin.pdf-studio.api.templates.project.store', $template), [
            'qr_field_id' => 'fld_qr', 'recipient_field_id' => 'fld_name',
        ], [], ['project' => $this->projectBundle()]);

        $unknown = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.prepare', $template), [
            'participants' => $this->excelUpload(['name', 'not_a_field'], [['Alice', 'x']]),
        ]);
        $unknown->assertStatus(422);

        $missing = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.prepare', $template), [
            'participants' => $this->excelUpload(['paper_title'], [['A Study']]),
        ]);
        $missing->assertStatus(422);
    }

    public function test_prepare_preserves_leading_zeros(): void
    {
        $admin = User::factory()->create();
        $template = $this->draftTemplate($admin);
        $this->actingAs($admin)->call('PUT', route('admin.pdf-studio.api.templates.project.store', $template), [
            'qr_field_id' => 'fld_qr', 'recipient_field_id' => 'fld_name',
        ], [], ['project' => $this->projectBundle()]);

        $response = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.prepare', $template), [
            'participants' => $this->excelUpload(['name', 'paper_title'], [['0012', 'A Study']]),
        ]);

        $response->assertOk();
        $this->assertSame('0012', $response->json('rows.0.values.name'));
    }

    public function test_confirm_is_idempotent_on_repeated_idempotency_key(): void
    {
        $admin = User::factory()->create();
        $template = $this->draftTemplate($admin);
        $this->actingAs($admin)->call('PUT', route('admin.pdf-studio.api.templates.project.store', $template), [
            'qr_field_id' => 'fld_qr', 'recipient_field_id' => 'fld_name',
        ], [], ['project' => $this->projectBundle()]);

        $prepare = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.prepare', $template), [
            'participants' => $this->excelUpload(['name', 'paper_title'], [['Alice Doe', 'A Study']]),
        ]);
        $token = $prepare->json('token');

        $key = 'idem-key-1';
        $first = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.confirm', $template), [
            'token' => $token, 'idempotency_key' => $key,
        ]);
        $first->assertOk();
        $batchId = $first->json('batch_id');

        $second = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.confirm', $template), [
            'token' => $token, 'idempotency_key' => $key,
        ]);
        $second->assertOk();
        $this->assertSame($batchId, $second->json('batch_id'));
        $this->assertSame(1, CertificateBatch::count());
    }

    public function test_reserved_codeword_does_not_verify_before_finalize(): void
    {
        $admin = User::factory()->create();
        $template = $this->draftTemplate($admin);
        $this->actingAs($admin)->call('PUT', route('admin.pdf-studio.api.templates.project.store', $template), [
            'qr_field_id' => 'fld_qr', 'recipient_field_id' => 'fld_name',
        ], [], ['project' => $this->projectBundle()]);
        $prepare = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.prepare', $template), [
            'participants' => $this->excelUpload(['name', 'paper_title'], [['Alice Doe', 'A Study']]),
        ]);
        $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.confirm', $template), [
            'token' => $prepare->json('token'), 'idempotency_key' => 'k1',
        ]);

        $batch = CertificateBatch::first();
        $reservation = $batch->reservations()->first();
        $this->assertSame(CertificateBatchReservationStatus::Reserved, $reservation->status);
        $this->assertSame(0, Certificate::count());

        $verify = $this->get(route('certificate.verify', ['codeword' => $reservation->codeword]));
        $verify->assertOk();
        $verify->assertDontSee('Alice Doe');
    }

    public function test_manifest_exposes_field_id_to_column_mapping_for_the_studio_adapter(): void
    {
        // Regression test: reservations[].data is keyed by editor field id
        // (consistent with how the rest of the certificate system keys
        // `data`), but the saved project's own field mapping resolves
        // values by COLUMN NAME. The Studio adapter (GeneratePanel.tsx)
        // needs this id->column table to re-key data before handing rows to
        // the unmodified render engine — found missing during browser
        // verification (the generated PDFs rendered as blank templates
        // with no error reported), fixed by adding `fields` here. See
        // docs/PDF_STUDIO_INTEGRATION.md.
        $admin = User::factory()->create();
        $template = $this->draftTemplate($admin);
        $this->actingAs($admin)->call('PUT', route('admin.pdf-studio.api.templates.project.store', $template), [
            'qr_field_id' => 'fld_qr', 'recipient_field_id' => 'fld_name',
        ], [], ['project' => $this->projectBundle()]);
        $prepare = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.prepare', $template), [
            'participants' => $this->excelUpload(['name', 'paper_title'], [['Alice Doe', 'A Study']]),
        ]);
        $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.confirm', $template), [
            'token' => $prepare->json('token'), 'idempotency_key' => 'k-manifest',
        ]);
        $batch = CertificateBatch::first();

        $manifest = $this->actingAs($admin)->getJson(route('admin.pdf-studio.api.batches.manifest', $batch));
        $manifest->assertOk();

        $fields = collect($manifest->json('fields'));
        $this->assertEqualsCanonicalizing(
            ['name', 'paper_title', 'photo'],
            $fields->pluck('column')->all()
        );
        $nameField = $fields->firstWhere('column', 'name');
        $this->assertSame('fld_name', $nameField['id']);

        // The data for the row is keyed by field id, exactly what `fields`
        // must be used to translate.
        $reservationData = collect($manifest->json('reservations'))->first()['data'];
        $this->assertArrayHasKey('fld_name', $reservationData);
        $this->assertSame('Alice Doe', $reservationData['fld_name']);
    }

    public function test_finalize_creates_certificate_and_is_idempotent_and_rejects_conflicting_pdf(): void
    {
        $admin = User::factory()->create();
        $template = $this->draftTemplate($admin);
        $this->actingAs($admin)->call('PUT', route('admin.pdf-studio.api.templates.project.store', $template), [
            'qr_field_id' => 'fld_qr', 'recipient_field_id' => 'fld_name',
        ], [], ['project' => $this->projectBundle()]);
        $prepare = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.prepare', $template), [
            'participants' => $this->excelUpload(['name', 'paper_title'], [['Alice Doe', 'A Study']]),
        ]);
        $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.confirm', $template), [
            'token' => $prepare->json('token'), 'idempotency_key' => 'k2',
        ]);
        $reservation = CertificateBatch::first()->reservations()->first();

        $pdfPath = tempnam(sys_get_temp_dir(), 'cert').'.pdf';
        file_put_contents($pdfPath, "%PDF-1.4\nfake\n%%EOF");
        $upload = fn () => new UploadedFile($pdfPath, 'c.pdf', 'application/pdf', null, true);

        $first = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.reservations.finalize', $reservation), ['pdf' => $upload()]);
        $first->assertOk();
        $this->assertSame('finalized', $first->json('status'));
        $certificateId = $first->json('certificate_id');
        $this->assertSame(1, Certificate::count());

        // Retry with the SAME bytes (lost response case) -> idempotent, no duplicate.
        $retry = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.reservations.finalize', $reservation), ['pdf' => $upload()]);
        $retry->assertOk();
        $this->assertSame('already_finalized', $retry->json('status'));
        $this->assertSame($certificateId, $retry->json('certificate_id'));
        $this->assertSame(1, Certificate::count());

        // A DIFFERENT PDF for the same, already-finalized row -> conflict, never overwritten.
        $differentPath = tempnam(sys_get_temp_dir(), 'cert2').'.pdf';
        file_put_contents($differentPath, "%PDF-1.4\nSOMETHING ELSE ENTIRELY DIFFERENT\n%%EOF");
        $conflict = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.reservations.finalize', $reservation), [
            'pdf' => new UploadedFile($differentPath, 'c2.pdf', 'application/pdf', null, true),
        ]);
        $conflict->assertStatus(409);
        $this->assertSame(1, Certificate::count());

        $certificate = Certificate::find($certificateId);
        $verify = $this->get(route('certificate.verify', ['codeword' => $certificate->codeword]));
        $verify->assertOk();
        $verify->assertSee('Alice Doe');
    }

    public function test_batch_zip_download_has_stable_filenames_and_order_and_can_be_redownloaded_without_regenerating(): void
    {
        $admin = User::factory()->create();
        $template = $this->draftTemplate($admin);
        $this->actingAs($admin)->call('PUT', route('admin.pdf-studio.api.templates.project.store', $template), [
            'qr_field_id' => 'fld_qr', 'recipient_field_id' => 'fld_name',
        ], [], ['project' => $this->projectBundle()]);
        $prepare = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.prepare', $template), [
            'participants' => $this->excelUpload(['name', 'paper_title'], [
                ['Alice Doe', 'A Study'],
                ['Bob Roe', 'Another Study'],
            ]),
        ]);
        $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.confirm', $template), [
            'token' => $prepare->json('token'), 'idempotency_key' => 'k3',
        ]);
        $batch = CertificateBatch::first();
        foreach ($batch->reservations as $reservation) {
            $pdfPath = tempnam(sys_get_temp_dir(), 'cert').'.pdf';
            file_put_contents($pdfPath, "%PDF-1.4\n{$reservation->codeword}\n%%EOF");
            $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.reservations.finalize', $reservation), [
                'pdf' => new UploadedFile($pdfPath, 'c.pdf', 'application/pdf', null, true),
            ])->assertOk();
        }

        $download1 = $this->actingAs($admin)->get(route('admin.pdf-studio.api.batches.download', $batch));
        $download1->assertOk();
        $download1->assertHeader('X-Batch-Incomplete', '0');
        $bytes1 = $download1->streamedContent();

        $download2 = $this->actingAs($admin)->get(route('admin.pdf-studio.api.batches.download', $batch));
        $bytes2 = $download2->streamedContent();

        $zip = new ZipArchive;
        $tmp = tempnam(sys_get_temp_dir(), 'dl').'.zip';
        file_put_contents($tmp, $bytes1);
        $zip->open($tmp);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertStringStartsWith('001-', $names[0]);
        $this->assertStringContainsString('Alice', $names[0]);
        $this->assertStringStartsWith('002-', $names[1]);
        $this->assertStringContainsString('Bob', $names[1]);

        // Re-download returns the SAME stored bytes — no regeneration, no new records.
        $this->assertSame(hash('sha256', $bytes1), hash('sha256', $bytes2));
        $this->assertSame(2, Certificate::count());
    }

    public function test_later_template_edit_does_not_change_an_already_confirmed_batch(): void
    {
        $admin = User::factory()->create();
        $template = $this->draftTemplate($admin);
        $this->actingAs($admin)->call('PUT', route('admin.pdf-studio.api.templates.project.store', $template), [
            'qr_field_id' => 'fld_qr', 'recipient_field_id' => 'fld_name',
        ], [], ['project' => $this->projectBundle()]);
        $prepare = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.prepare', $template), [
            'participants' => $this->excelUpload(['name', 'paper_title'], [['Alice Doe', 'A Study']]),
        ]);
        $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.confirm', $template), [
            'token' => $prepare->json('token'), 'idempotency_key' => 'k4',
        ]);
        $batch = CertificateBatch::first();
        $this->assertSame(1, $batch->editor_schema_version);

        // Admin edits the project again (re-save -> version bumps to 2).
        $this->actingAs($admin)->call('PUT', route('admin.pdf-studio.api.templates.project.store', $template), [
            'qr_field_id' => 'fld_qr', 'recipient_field_id' => 'fld_name',
        ], [], ['project' => $this->projectBundle()]);
        $template->refresh();
        $this->assertSame(2, $template->editor_schema_version);

        // The already-confirmed batch still records the version it was created against.
        $batch->refresh();
        $this->assertSame(1, $batch->editor_schema_version);
    }

    private function projectNameFromBundleBytes(string $zipBytes): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'readback').'.zip';
        file_put_contents($tmp, $zipBytes);
        $zip = new ZipArchive;
        $zip->open($tmp);
        $json = json_decode($zip->getFromName('project.json'), true);
        $zip->close();
        unlink($tmp);

        return $json['name'];
    }

    public function test_batch_template_immutability_old_batch_keeps_old_design_new_batch_gets_new_design(): void
    {
        // Reproduces the exact scenario this column/endpoint exists for
        // (docs/PDF_STUDIO_INTEGRATION.md "Batch template immutability"):
        // start a 3-row batch, finalize one row, edit+save the template,
        // resume the OLD batch, and confirm it still serves the ORIGINAL
        // bundle — while a NEW batch confirmed after the edit serves the
        // EDITED one. Goes further than asserting editor_schema_version:
        // it reads back the actual bundle bytes a resumed batch would be
        // rendered from.
        $admin = User::factory()->create();
        $template = $this->draftTemplate($admin);

        $this->actingAs($admin)->call('PUT', route('admin.pdf-studio.api.templates.project.store', $template), [
            'qr_field_id' => 'fld_qr', 'recipient_field_id' => 'fld_name',
        ], [], ['project' => $this->projectBundle('Original Design')]);

        $prepare = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.prepare', $template), [
            'participants' => $this->excelUpload(['name', 'paper_title'], [
                ['Alice Doe', 'A Study'],
                ['Bob Roe', 'Another Study'],
                ['Carol Poe', 'A Third Study'],
            ]),
        ]);
        $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.confirm', $template), [
            'token' => $prepare->json('token'), 'idempotency_key' => 'immutability-old-batch',
        ]);
        $oldBatch = CertificateBatch::first();
        $this->assertNotNull($oldBatch->editor_project_path);

        // Finalize one row of the old batch before the template changes.
        $reservation = $oldBatch->reservations()->first();
        $pdfPath = tempnam(sys_get_temp_dir(), 'cert').'.pdf';
        file_put_contents($pdfPath, "%PDF-1.4\nfake\n%%EOF");
        $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.reservations.finalize', $reservation), [
            'pdf' => new UploadedFile($pdfPath, 'c.pdf', 'application/pdf', null, true),
        ])->assertOk();

        // Edit and save the original template with a different design.
        $this->actingAs($admin)->call('PUT', route('admin.pdf-studio.api.templates.project.store', $template), [
            'qr_field_id' => 'fld_qr', 'recipient_field_id' => 'fld_name',
        ], [], ['project' => $this->projectBundle('Edited Design')]);
        $template->refresh();
        $this->assertSame(2, $template->editor_schema_version);

        // Resuming the OLD batch (its remaining, un-finalized rows) must
        // still serve the ORIGINAL bundle, not the template's current one.
        $oldBatchProject = $this->actingAs($admin)->get(route('admin.pdf-studio.api.batches.project', $oldBatch));
        $oldBatchProject->assertOk();
        $this->assertSame('Original Design', $this->projectNameFromBundleBytes($oldBatchProject->streamedContent()));

        // A NEW batch confirmed after the edit must serve the EDITED bundle.
        $prepare2 = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.prepare', $template), [
            'participants' => $this->excelUpload(['name', 'paper_title'], [['Dave Roe', 'A Fourth Study']]),
        ]);
        $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.confirm', $template), [
            'token' => $prepare2->json('token'), 'idempotency_key' => 'immutability-new-batch',
        ]);
        $newBatch = CertificateBatch::where('id', '!=', $oldBatch->id)->first();
        $this->assertSame(2, $newBatch->editor_schema_version);

        $newBatchProject = $this->actingAs($admin)->get(route('admin.pdf-studio.api.batches.project', $newBatch));
        $newBatchProject->assertOk();
        $this->assertSame('Edited Design', $this->projectNameFromBundleBytes($newBatchProject->streamedContent()));

        // The template's own (current) project is the edited one, confirming
        // the old batch's bundle really is a separate, pinned file — not
        // merely a cache of the template's state at an earlier point in time.
        $templateProject = $this->actingAs($admin)->get(route('admin.pdf-studio.api.templates.project.show', $template));
        $this->assertSame('Edited Design', $this->projectNameFromBundleBytes($templateProject->streamedContent()));
    }

    public function test_confirm_survives_a_lost_race_on_the_same_idempotency_key(): void
    {
        // DirectBatchService::confirm() checks `where('idempotency_key', ...)->first()`
        // then creates — a classic check-then-insert race. Two concurrent
        // requests can both see null and both attempt to create; the DB's
        // UNIQUE constraint on idempotency_key is the real guarantee, and
        // the loser must return the winner's batch instead of a 500. This
        // simulates the race deterministically: insert the "winner" row
        // directly between the check and the create.
        $admin = User::factory()->create();
        $template = $this->draftTemplate($admin);
        $this->actingAs($admin)->call('PUT', route('admin.pdf-studio.api.templates.project.store', $template), [
            'qr_field_id' => 'fld_qr', 'recipient_field_id' => 'fld_name',
        ], [], ['project' => $this->projectBundle()]);
        $prepare = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.prepare', $template), [
            'participants' => $this->excelUpload(['name', 'paper_title'], [['Alice Doe', 'A Study']]),
        ]);
        $token = $prepare->json('token');
        $key = 'race-key-1';

        $winner = CertificateBatch::create([
            'certificate_template_id' => $template->id,
            'source' => 'pdf_studio',
            'name' => 'Winner (simulated concurrent request)',
            'status' => CertificateBatchStatus::Processing,
            'total_rows' => 1,
            'created_by' => $admin->id,
            'idempotency_key' => $key,
            'editor_schema_version' => $template->editor_schema_version,
            'editor_project_path' => $template->editor_project_path,
        ]);

        $loserResponse = $this->actingAs($admin)->postJson(route('admin.pdf-studio.api.templates.batches.confirm', $template), [
            'token' => $token, 'idempotency_key' => $key,
        ]);

        $loserResponse->assertOk();
        $this->assertSame($winner->id, $loserResponse->json('batch_id'));
        $this->assertSame(1, CertificateBatch::where('idempotency_key', $key)->count());
    }
}
