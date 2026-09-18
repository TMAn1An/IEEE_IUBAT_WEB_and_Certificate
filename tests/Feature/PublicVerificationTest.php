<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Enums\CertificateTemplateStatus;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\TemplateField;
use App\Models\User;
use App\Services\Certificates\CertificateSnapshotService;
use App\Services\Certificates\Import\CertificateImportService;
use App\Services\Certificates\Import\CertificateImportValidator;
use App\Services\Certificates\Import\ImportRowResult;
use App\Services\Certificates\Pdf\PdfPageBox;
use App\Services\Certificates\QrCodeService;
use App\Services\Certificates\SimpleCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 6 (public verification) required tests -- see docs/CHANGELOG.md.
 * Covers all three certificate-creation paths (QR-only, Excel-imported,
 * Phase 5 PDF-path) resolving through the same public
 * GET /certificate/verify/{codeword} route.
 */
class PublicVerificationTest extends TestCase
{
    use RefreshDatabase;

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
            'show_on_verification' => true,
        ]);

        TemplateField::factory()->for($template, 'template')->create([
            'field_key' => 'role',
            'label' => 'Role',
            'field_type' => 'text',
            'is_required' => true,
            'show_on_verification' => true,
        ]);

        TemplateField::factory()->for($template, 'template')->create([
            'field_key' => 'email',
            'label' => 'Email',
            'field_type' => 'text',
            'is_required' => false,
            'show_on_verification' => false,
        ]);

        return $template->fresh(['fields']);
    }

    private function verifyUrl(string $codeword): string
    {
        return "/certificate/verify/{$codeword}";
    }

    public function test_route_requires_no_login(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $certificate = app(SimpleCertificateService::class)->issue($template, [
            'recipient_name' => 'Jane Doe', 'role' => 'Speaker', 'email' => 'jane@example.com',
        ], $manager);

        // No actingAs() anywhere in this test -- a genuinely guest request.
        $this->get($this->verifyUrl($certificate->codeword))
            ->assertOk()
            ->assertDontSee('/admin/login');
    }

    public function test_valid_manual_qr_certificate_verifies(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $certificate = app(SimpleCertificateService::class)->issue($template, [
            'recipient_name' => 'Jane Doe', 'role' => 'Speaker', 'email' => 'jane@example.com',
        ], $manager);

        $response = $this->get($this->verifyUrl($certificate->codeword));

        $response->assertOk()
            ->assertSee('Certificate Verified')
            ->assertSee($certificate->certificate_number)
            ->assertSee('Jane Doe')
            ->assertSee('Speaker');
    }

    public function test_valid_excel_imported_certificate_verifies_with_preserved_codeword(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);

        $rows = [['Jane Import', 'Speaker', '']];
        $mapping = [0 => 'recipient_name', 1 => 'role'];
        $validator = app(CertificateImportValidator::class);
        $validated = $validator->validateRows($template, ['Name', 'Role', 'Email'], $rows, $mapping);

        // Simulate a preserved historical codeword directly (import HTTP flow
        // is covered by tests/Feature/Admin/CertificateExcelImportTest.php;
        // this test is about verification, not re-testing the importer).
        $row = $validated[0];
        $preserved = new ImportRowResult(
            rowNumber: $row->rowNumber,
            errors: [],
            recipientName: $row->recipientName,
            fieldValues: $row->fieldValues,
            codeword: 'legacycode123',
            certificateNumber: null,
        );

        app(CertificateImportService::class)->import($template, [$preserved], $manager);
        $certificate = Certificate::where('codeword', 'legacycode123')->firstOrFail();

        $this->get($this->verifyUrl('legacycode123'))
            ->assertOk()
            ->assertSee('Certificate Verified')
            ->assertSee($certificate->certificate_number)
            ->assertSee('Jane Import');
    }

    public function test_existing_phase5_certificate_verifies_via_snapshot(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $snapshot = app(CertificateSnapshotService::class);

        $certificate = Certificate::factory()->create([
            'certificate_template_id' => $template->id,
            'recipient_name' => 'Snapshot Person',
            'data' => ['recipient_name' => 'Snapshot Person', 'role' => 'Chair', 'email' => 'hidden@example.com'],
            'template_snapshot' => $snapshot->templateSnapshot($template),
            'layout_snapshot' => $snapshot->layoutSnapshot($template, new PdfPageBox(0, 0, 800, 600)),
            'pdf_path' => 'certificates/2026/fake-uuid.pdf',
        ]);

        $response = $this->get($this->verifyUrl($certificate->codeword));

        $response->assertOk()
            ->assertSee('Certificate Verified')
            ->assertSee('Snapshot Person')
            ->assertSee('Chair')
            ->assertDontSee('hidden@example.com')
            ->assertDontSee('certificates/2026/fake-uuid.pdf');
    }

    public function test_public_and_hidden_dynamic_fields(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $certificate = app(SimpleCertificateService::class)->issue($template, [
            'recipient_name' => 'Jane Doe', 'role' => 'Speaker', 'email' => 'secret@example.com',
        ], $manager);

        $response = $this->get($this->verifyUrl($certificate->codeword));

        $response->assertSee('Speaker')->assertDontSee('secret@example.com');
    }

    public function test_raw_json_and_internal_fields_never_displayed(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $certificate = app(SimpleCertificateService::class)->issue($template, [
            'recipient_name' => 'Jane Doe', 'role' => 'Speaker', 'email' => 'secret@example.com',
        ], $manager);

        $response = $this->get($this->verifyUrl($certificate->codeword));
        $content = $response->getContent();

        $this->assertStringNotContainsString($certificate->codeword, $content);
        // Not a plain digit check (e.g. the id itself) -- any short numeric
        // substring coincidentally appears in dates/etc. ("2026" contains
        // "6"). The DTO passed to the view (VerificationResult) has no id
        // field at all, so leaking it isn't just untested, it's structurally
        // impossible -- see App\Services\Certificates\Verification\
        // VerificationResult's docblock.
        $this->assertStringNotContainsString('field_key', $content);
        $this->assertStringNotContainsString('"role":', $content);
    }

    public function test_unknown_codeword_shows_not_verified(): void
    {
        $response = $this->get($this->verifyUrl(str_repeat('a', 64)));

        $response->assertOk()
            ->assertSee('Certificate Not Verified')
            ->assertDontSee('Certificate Verified');
    }

    public function test_not_verified_result_leaks_no_recipient_information(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        app(SimpleCertificateService::class)->issue($template, [
            'recipient_name' => 'Should Not Appear', 'role' => 'Speaker', 'email' => 'x@example.com',
        ], $manager);

        $response = $this->get($this->verifyUrl(str_repeat('b', 64)));

        $response->assertDontSee('Should Not Appear');
    }

    public function test_very_long_malformed_codeword_is_handled_safely(): void
    {
        $this->get($this->verifyUrl(str_repeat('a', 500)))->assertNotFound();
        $this->get($this->verifyUrl('a'))->assertNotFound(); // below the 4-char minimum
    }

    public function test_revoked_certificate_is_not_shown_as_verified(): void
    {
        $certificate = Certificate::factory()->create(['status' => CertificateStatus::Revoked]);

        $response = $this->get($this->verifyUrl($certificate->codeword));

        $response->assertOk()
            ->assertSee('Certificate Revoked')
            ->assertSee($certificate->certificate_number)
            ->assertDontSee('Certificate Verified')
            ->assertDontSee($certificate->recipient_name);
    }

    public function test_reissued_status_does_not_verify(): void
    {
        $certificate = Certificate::factory()->create(['status' => CertificateStatus::Reissued]);

        $this->get($this->verifyUrl($certificate->codeword))
            ->assertSee('Certificate Not Verified')
            ->assertDontSee('Certificate Verified');
    }

    public function test_qr_service_url_resolves_to_the_verification_route(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithFields($manager);
        $certificate = app(SimpleCertificateService::class)->issue($template, [
            'recipient_name' => 'Jane Doe', 'role' => 'Speaker', 'email' => 'x@example.com',
        ], $manager);

        $url = app(QrCodeService::class)->verificationUrlFor($certificate);
        $path = parse_url($url, PHP_URL_PATH);

        $this->assertSame("/certificate/verify/{$certificate->codeword}", $path);
        $this->get($path)->assertOk()->assertSee('Certificate Verified');
    }

    public function test_noindex_header_and_meta_tag_present(): void
    {
        $certificate = Certificate::factory()->create();

        $response = $this->get($this->verifyUrl($certificate->codeword));

        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('name="robots" content="noindex,nofollow"', $response->getContent());
    }

    public function test_no_store_cache_header_present(): void
    {
        $certificate = Certificate::factory()->create();

        $this->get($this->verifyUrl($certificate->codeword))
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_verification_route_has_throttle_middleware(): void
    {
        $route = Route::getRoutes()->getByName('certificate.verify');

        $this->assertNotNull($route);
        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
    }
}
