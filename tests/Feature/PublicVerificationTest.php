<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Enums\CertificateTemplateStatus;
use App\Enums\QrCertificateStatus;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\QrCategory;
use App\Models\QrCategoryField;
use App\Models\QrCertificate;
use App\Models\TemplateField;
use App\Models\User;
use App\Services\Certificates\CertificateSnapshotService;
use App\Services\Certificates\Pdf\PdfPageBox;
use App\Services\Certificates\QrCodeService;
use App\Services\QrTool\Import\QrCategoryImportService;
use App\Services\QrTool\Import\QrCategoryImportValidator;
use App\Services\QrTool\QrCertificateIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Public verification tests -- covers all three certificate-creation paths
 * resolving through the same public GET /certificate/verify/{codeword}
 * route: the independent simple QR tool (QrCertificate, manual generation
 * and Excel import) and the advanced system (Certificate, Phase 5 PDF
 * path). See docs/CERTIFICATE_SYSTEM.md §Public verification and §Simple
 * QR tool.
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

    private function qrCategoryWithFields(User $creator): QrCategory
    {
        $category = QrCategory::factory()->for($creator, 'creator')->create(['event_name' => 'IEEE BECITHCON 2026']);

        QrCategoryField::factory()->for($category, 'category')->create([
            'key' => 'recipient_name', 'label' => 'Name', 'type' => 'text',
            'required' => true, 'is_recipient_name' => true, 'show_on_verification' => true,
        ]);
        QrCategoryField::factory()->for($category, 'category')->create([
            'key' => 'role', 'label' => 'Role', 'type' => 'text',
            'required' => true, 'show_on_verification' => true,
        ]);
        QrCategoryField::factory()->for($category, 'category')->create([
            'key' => 'session', 'label' => 'Session', 'type' => 'long_text',
            'required' => false, 'show_on_verification' => true,
        ]);

        return $category->fresh(['fields']);
    }

    private function verifyUrl(string $codeword): string
    {
        return "/certificate/verify/{$codeword}";
    }

    public function test_route_requires_no_login(): void
    {
        $certificate = Certificate::factory()->create();

        // No actingAs() anywhere in this test -- a genuinely guest request.
        $this->get($this->verifyUrl($certificate->codeword))
            ->assertOk()
            ->assertDontSee('/admin/login');
    }

    public function test_valid_simple_qr_certificate_verifies(): void
    {
        $manager = User::factory()->create();
        $category = $this->qrCategoryWithFields($manager);

        $certificate = app(QrCertificateIssuanceService::class)->issue($category, [
            'recipient_name' => 'Jane Doe', 'role' => 'Volunteer', 'session' => 'Track A',
        ], $manager);

        $response = $this->get($this->verifyUrl($certificate->codeword));

        $response->assertOk()
            ->assertSee('Certificate Verified')
            ->assertSee('Jane Doe')
            ->assertSee('Volunteer')
            ->assertSee('IEEE BECITHCON 2026');
    }

    public function test_valid_excel_imported_qr_certificate_verifies_with_preserved_codeword(): void
    {
        $manager = User::factory()->create();
        $category = $this->qrCategoryWithFields($manager);

        $rows = [['Jane Import', 'Volunteer', 'Track A']];
        $mapping = [0 => 'recipient_name', 1 => 'role', 2 => 'session'];
        $validated = app(QrCategoryImportValidator::class)->validateRows($category, $rows, $mapping);

        app(QrCategoryImportService::class)->import($category, $validated, $manager);
        $certificate = QrCertificate::where('recipient_name', 'Jane Import')->firstOrFail();

        $this->get($this->verifyUrl($certificate->codeword))
            ->assertOk()
            ->assertSee('Certificate Verified')
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

    public function test_public_and_hidden_dynamic_fields_for_simple_qr(): void
    {
        $manager = User::factory()->create();
        $category = $this->qrCategoryWithFields($manager);
        QrCategoryField::factory()->for($category, 'category')->create([
            'key' => 'email', 'label' => 'Email', 'type' => 'text',
            'required' => false, 'show_on_verification' => false,
        ]);

        $certificate = app(QrCertificateIssuanceService::class)->issue($category, [
            'recipient_name' => 'Jane Doe', 'role' => 'Volunteer', 'session' => 'Track A', 'email' => 'secret@example.com',
        ], $manager);

        $response = $this->get($this->verifyUrl($certificate->codeword));

        $response->assertSee('Volunteer')->assertDontSee('secret@example.com');
    }

    public function test_raw_json_and_internal_fields_never_displayed(): void
    {
        $manager = User::factory()->create();
        $category = $this->qrCategoryWithFields($manager);
        $certificate = app(QrCertificateIssuanceService::class)->issue($category, [
            'recipient_name' => 'Jane Doe', 'role' => 'Volunteer', 'session' => 'Track A',
        ], $manager);

        $response = $this->get($this->verifyUrl($certificate->codeword));
        $content = $response->getContent();

        $this->assertStringNotContainsString($certificate->codeword, $content);
        // The DTO passed to the view (VerificationResult) has no id field
        // at all, so leaking the raw numeric id isn't just untested, it's
        // structurally impossible -- see App\Services\Certificates\
        // Verification\VerificationResult's docblock.
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
        $category = $this->qrCategoryWithFields($manager);
        app(QrCertificateIssuanceService::class)->issue($category, [
            'recipient_name' => 'Should Not Appear', 'role' => 'Volunteer', 'session' => 'Track A',
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

    public function test_revoked_simple_qr_certificate_is_not_shown_as_verified(): void
    {
        $certificate = QrCertificate::factory()->create(['status' => QrCertificateStatus::Revoked]);

        $response = $this->get($this->verifyUrl($certificate->codeword));

        $response->assertOk()
            ->assertSee('Certificate Revoked')
            ->assertDontSee('Certificate Verified')
            ->assertDontSee($certificate->recipient_name);
    }

    public function test_qr_service_url_resolves_to_the_verification_route_for_both_sources(): void
    {
        $manager = User::factory()->create();

        $certificate = Certificate::factory()->create();
        $url = app(QrCodeService::class)->verificationUrlFor($certificate);
        $this->assertSame("/certificate/verify/{$certificate->codeword}", parse_url($url, PHP_URL_PATH));
        $this->get(parse_url($url, PHP_URL_PATH))->assertOk()->assertSee('Certificate Verified');

        $category = $this->qrCategoryWithFields($manager);
        $qrCertificate = app(QrCertificateIssuanceService::class)->issue($category, [
            'recipient_name' => 'Jane Doe', 'role' => 'Volunteer', 'session' => 'Track A',
        ], $manager);
        $qrUrl = app(QrCodeService::class)->verificationUrlForCodeword($qrCertificate->codeword);
        $this->assertSame("/certificate/verify/{$qrCertificate->codeword}", parse_url($qrUrl, PHP_URL_PATH));
        $this->get(parse_url($qrUrl, PHP_URL_PATH))->assertOk()->assertSee('Certificate Verified');
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
