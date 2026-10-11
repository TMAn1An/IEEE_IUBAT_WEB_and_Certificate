<?php

namespace Tests\Feature\Admin;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The certificate RECORDS browser (search/view/download) — the part of the
 * old Phase 5 single-issuance test suite that covers behavior
 * CertificateController still has after the admin workflow cleanup removed
 * its chooseTemplate/create/store actions. See
 * docs/PDF_STUDIO_INTEGRATION.md: every certificate PDF Certificates issues
 * lands in this same `certificates` table, so this browser is still the one
 * place to find/view/download/audit any of them, regardless of how they
 * were generated.
 */
class CertificateRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_certificate_listing_and_search_work(): void
    {
        $manager = User::factory()->create();
        Certificate::factory()->create(['certificate_number' => 'IEEE-IUBAT-2026-000111', 'recipient_name' => 'Alice Example']);
        Certificate::factory()->create(['certificate_number' => 'IEEE-IUBAT-2026-000222', 'recipient_name' => 'Bob Example']);

        $this->actingAs($manager)->get('/admin/certificates')->assertOk()->assertSee('Alice Example')->assertSee('Bob Example');

        $this->actingAs($manager)->get('/admin/certificates?search=000111')
            ->assertOk()->assertSee('Alice Example')->assertDontSee('Bob Example');

        $this->actingAs($manager)->get('/admin/certificates?search=Bob')
            ->assertOk()->assertSee('Bob Example')->assertDontSee('Alice Example');
    }

    public function test_certificate_detail_and_download_require_authorization(): void
    {
        $manager = User::factory()->create();
        $pdfPath = 'certificates/2026/test.pdf';
        Storage::disk('local')->put($pdfPath, "%PDF-1.4\nfake\n%%EOF");
        $certificate = Certificate::factory()->create(['pdf_path' => $pdfPath]);

        $this->get("/admin/certificates/{$certificate->id}")->assertRedirect('/admin/login');
        $this->get("/admin/certificates/{$certificate->id}/download")->assertRedirect('/admin/login');

        $this->actingAs($manager)->get("/admin/certificates/{$certificate->id}")
            ->assertOk()->assertSee($certificate->certificate_number);

        $this->actingAs($manager)->get("/admin/certificates/{$certificate->id}/download")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_deleted_certificates_list_is_super_admin_only(): void
    {
        $manager = User::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create();
        Certificate::factory()->create(['recipient_name' => 'Deleted Example'])->delete();

        $this->actingAs($manager)->get('/admin/certificates/deleted')->assertForbidden();
        $this->actingAs($superAdmin)->get('/admin/certificates/deleted')->assertOk()->assertSee('Deleted Example');
    }
}
