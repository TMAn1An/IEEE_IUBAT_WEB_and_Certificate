<?php

namespace Tests\Feature\Admin;

use App\Models\CertificateBatch;
use App\Models\CertificateTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The unified PDF Certificates entry flow — see
 * docs/PDF_STUDIO_INTEGRATION.md's "PDF Certificates entry flow". Covers
 * what changed in the admin workflow cleanup: a fresh database's empty
 * state, "New template" creating a template AND uploading its demo PDF in
 * one step then landing straight in the real editor (no legacy
 * template-manager detour), that uploaded PDF being preloaded for the
 * editor until a project is saved, legacy URL redirects, and the batch
 * history list.
 */
class PdfCertificatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function samplePdf(): UploadedFile
    {
        // A minimal but structurally valid single-page PDF (real %PDF-
        // header) — enough for StoreCertificateTemplateRequest's mimes:pdf
        // + magic-header checks.
        $contents = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 842 595]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF";

        return UploadedFile::fake()->createWithContent('demo-certificate.pdf', $contents);
    }

    public function test_a_fresh_database_shows_an_empty_state_with_a_new_template_action(): void
    {
        $manager = User::factory()->create();

        $response = $this->actingAs($manager)->get('/admin/pdf-certificates');

        $response->assertOk();
        $response->assertSee('No templates yet');
        $response->assertSee(route('admin.pdf-certificates.create'), false);
    }

    public function test_dashboard_links_directly_to_pdf_certificates_and_qr_generator(): void
    {
        $manager = User::factory()->create();

        $response = $this->actingAs($manager)->get('/admin');

        $response->assertOk();
        $response->assertSee(route('admin.pdf-certificates.index'), false);
        $response->assertSee(route('admin.qr.generate.show'), false);
    }

    public function test_creating_a_template_uploads_the_pdf_and_redirects_straight_into_the_editor_no_legacy_detour(): void
    {
        $manager = User::factory()->create();

        $response = $this->actingAs($manager)->post('/admin/pdf-certificates', [
            'name' => 'BECITHCON 2026 Speaker',
            'demo_pdf' => $this->samplePdf(),
        ]);

        $template = CertificateTemplate::where('name', 'BECITHCON 2026 Speaker')->first();
        $this->assertNotNull($template);
        $this->assertNotNull($template->source_pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($template->source_pdf_path));

        // Straight to the real editor -- never a legacy template-manager page.
        $response->assertRedirect(route('admin.pdf-studio.show', $template));
    }

    public function test_uploaded_pdf_is_preloaded_for_the_editor_only_until_a_project_is_saved(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post('/admin/pdf-certificates', [
            'name' => 'Preload Check',
            'demo_pdf' => $this->samplePdf(),
        ]);
        $template = CertificateTemplate::where('name', 'Preload Check')->first();

        $page = $this->actingAs($manager)->get(route('admin.pdf-studio.show', $template));
        $page->assertOk();
        $page->assertSee(route('admin.pdf-studio.api.templates.source-pdf', $template), false);

        // Fetching that URL actually returns the uploaded PDF bytes.
        $this->actingAs($manager)->get(route('admin.pdf-studio.api.templates.source-pdf', $template))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Once a project is saved, the preload URL must no longer be advertised.
        $template->update(['editor_project_path' => 'certificate-templates/'.$template->id.'/project-x.pdftemplate']);
        $this->actingAs($manager)->get(route('admin.pdf-studio.show', $template))
            ->assertOk()
            ->assertDontSee(route('admin.pdf-studio.api.templates.source-pdf', $template), false);
    }

    public function test_legacy_template_and_single_issuance_urls_redirect_to_pdf_certificates(): void
    {
        $manager = User::factory()->create();
        $template = CertificateTemplate::factory()->for($manager, 'creator')->create();

        $this->actingAs($manager)->get('/admin/templates')->assertRedirect(route('admin.pdf-certificates.index'));
        $this->actingAs($manager)->get('/admin/templates/create')->assertRedirect(route('admin.pdf-certificates.create'));
        $this->actingAs($manager)->get("/admin/templates/{$template->id}/edit")->assertRedirect(route('admin.pdf-studio.show', $template));
        $this->actingAs($manager)->get('/admin/certificates/issue')->assertRedirect(route('admin.pdf-certificates.index'));
    }

    public function test_removed_mutation_endpoints_are_gone(): void
    {
        $manager = User::factory()->create();
        $template = CertificateTemplate::factory()->for($manager, 'creator')->create();

        // The old manual designer/field-CRUD/background-upload/single-issuance
        // POST endpoints no longer exist at all -- not redirected, just gone.
        $this->actingAs($manager)->post("/admin/templates/{$template->id}/fields", [])->assertNotFound();
        $this->actingAs($manager)->post("/admin/templates/{$template->id}/designer", [])->assertNotFound();
        $this->actingAs($manager)->post("/admin/templates/{$template->id}/background", [])->assertNotFound();
        $this->actingAs($manager)->post("/admin/templates/{$template->id}/activate")->assertNotFound();
        $this->actingAs($manager)->post("/admin/certificates/issue/{$template->id}", [])->assertNotFound();
        $this->actingAs($manager)->post('/admin/bulk-generation', [])->assertNotFound();
    }

    public function test_batch_history_lists_batches_with_resume_and_download_links(): void
    {
        $manager = User::factory()->create();
        $template = CertificateTemplate::factory()->for($manager, 'creator')->create();
        $batch = CertificateBatch::factory()->for($template, 'template')->create([
            'source' => 'pdf_studio',
            'created_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->get(route('admin.pdf-certificates.batches', $template));

        $response->assertOk();
        $response->assertSee(route('admin.pdf-studio.show-batch', [$template, $batch]), false);
        $response->assertSee(route('admin.pdf-studio.api.batches.download', $batch), false);
    }

    public function test_archiving_a_template_hides_it_from_the_list_but_keeps_it_usable(): void
    {
        $manager = User::factory()->create();
        $template = CertificateTemplate::factory()->for($manager, 'creator')->create();

        $this->actingAs($manager)->post(route('admin.pdf-certificates.archive', $template))->assertRedirect(route('admin.pdf-certificates.index'));

        $this->actingAs($manager)->get('/admin/pdf-certificates')->assertDontSee($template->name);
        // Still directly reachable -- archiving only declutters the list.
        $this->actingAs($manager)->get(route('admin.pdf-studio.show', $template))->assertOk();
    }

    public function test_edit_page_renders_and_updates_name_and_slug(): void
    {
        $manager = User::factory()->create();
        $template = CertificateTemplate::factory()->for($manager, 'creator')->create(['name' => 'Old Name']);

        $this->actingAs($manager)->get(route('admin.pdf-certificates.edit', $template))
            ->assertOk()->assertSee('Old Name');

        $this->actingAs($manager)->patch(route('admin.pdf-certificates.update', $template), [
            'name' => 'New Name', 'slug' => $template->slug, 'description' => '',
        ])->assertRedirect(route('admin.pdf-certificates.edit', $template));

        $this->assertSame('New Name', $template->fresh()->name);
    }

    public function test_guest_and_unauthorized_access_are_blocked(): void
    {
        $this->get('/admin/pdf-certificates')->assertRedirect('/admin/login');

        $inactive = User::factory()->inactive()->create();
        $this->actingAs($inactive)->get('/admin/pdf-certificates')->assertRedirect('/admin/login');
    }
}
