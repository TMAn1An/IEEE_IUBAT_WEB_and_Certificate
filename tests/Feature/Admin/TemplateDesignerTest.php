<?php

namespace Tests\Feature\Admin;

use App\Models\CertificateTemplate;
use App\Models\TemplateField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Phase 4 required tests (see docs/CHANGELOG.md). Kept to the essentials
 * per the instruction not to build broad CRUD test suites — this exercises
 * the designer's save/validate/persist pipeline directly via
 * SaveTemplateLayoutRequest's payload shape, the same one
 * public/js/admin/template-designer.js builds client-side. The full
 * end-to-end flow (upload the actual demo Canva PDF, drag/resize in a real
 * browser) was verified manually — see the Phase 4 completion report.
 */
class TemplateDesignerTest extends TestCase
{
    use RefreshDatabase;

    private function samplePdf(): UploadedFile
    {
        // A minimal but structurally valid single-page PDF (real %PDF- header,
        // real xref/trailer) — enough for UploadTemplateBackgroundRequest's
        // mimes:pdf + magic-header checks, which is all Phase 4 validates
        // server-side (see docs/CERTIFICATE_SYSTEM.md §Background handling
        // for why no FPDI/Imagick parsing happens here).
        $contents = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 842 595]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF";

        return UploadedFile::fake()->createWithContent('sample.pdf', $contents);
    }

    private function templateWithBackground(User $creator): CertificateTemplate
    {
        $template = CertificateTemplate::factory()->for($creator, 'creator')->create();
        $template->update([
            'source_pdf_path' => 'certificate-templates/'.$template->id.'/fake.pdf',
            'original_filename' => 'sample.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 1024,
        ]);

        return $template->fresh();
    }

    public function test_authorized_certificate_manager_can_open_designer(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithBackground($manager);

        $this->actingAs($manager)
            ->get("/admin/templates/{$template->id}/designer")
            ->assertOk()
            ->assertSee('DESIGNER_CONFIG', false);
    }

    public function test_unauthorized_user_cannot_open_designer(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithBackground($manager);

        $this->get("/admin/templates/{$template->id}/designer")->assertRedirect('/admin/login');

        $inactive = User::factory()->inactive()->create();
        $this->actingAs($inactive)->get("/admin/templates/{$template->id}/designer")->assertRedirect('/admin/login');
    }

    public function test_layout_saves_and_persists_dynamic_field_placement_and_styling(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithBackground($manager);
        $field = TemplateField::factory()->for($template, 'template')->create(['field_type' => 'text']);

        $payload = $this->layoutPayload($template, [
            'fields' => [[
                'id' => $field->id,
                'position' => ['x' => 100, 'y' => 200, 'width' => 300, 'height' => 50],
                'style' => ['font_size' => 28, 'font_weight' => 'bold', 'alignment' => 'center', 'line_height' => 1.4, 'color' => '#112233', 'wrap' => true],
            ]],
        ]);

        $response = $this->actingAs($manager)
            ->post("/admin/templates/{$template->id}/designer", ['layout_json' => json_encode($payload)]);

        $response->assertRedirect();
        $field->refresh();
        $this->assertEquals(['x' => 100.0, 'y' => 200.0, 'width' => 300.0, 'height' => 50.0], $field->position);
        $this->assertEquals('bold', $field->style['font_weight']);
        $this->assertEquals('#112233', $field->style['color']);

        // Persists across a fresh request (simulates "reload the page").
        $this->actingAs($manager)
            ->get("/admin/templates/{$template->id}/designer")
            ->assertSee('"font_weight":"bold"', false)
            ->assertSee('"color":"#112233"', false);
    }

    public function test_certificate_number_and_qr_code_system_element_placement_persists(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithBackground($manager);

        $payload = $this->layoutPayload($template, [
            'certificate_number' => ['x' => 40, 'y' => 30, 'width' => 220, 'height' => 24, 'style' => ['font_size' => 12, 'alignment' => 'left']],
            'qr_code' => ['x' => 700, 'y' => 20, 'width' => 90, 'height' => 90],
        ]);

        $this->actingAs($manager)->post("/admin/templates/{$template->id}/designer", ['layout_json' => json_encode($payload)]);

        $template->refresh();
        $this->assertEquals(40.0, $template->certificate_number_layout['x']);
        $this->assertEquals('left', $template->certificate_number_layout['style']['alignment']);
        $this->assertEquals(90.0, $template->qr_code_layout['width']);
        $this->assertEquals(90.0, $template->qr_code_layout['height']);
    }

    public function test_qr_code_height_always_matches_width_regardless_of_submitted_value(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithBackground($manager);

        $payload = $this->layoutPayload($template, [
            'qr_code' => ['x' => 10, 'y' => 10, 'width' => 80, 'height' => 300],
        ]);

        $this->actingAs($manager)->post("/admin/templates/{$template->id}/designer", ['layout_json' => json_encode($payload)]);

        $this->assertEquals(80.0, $template->fresh()->qr_code_layout['height']);
    }

    public function test_invalid_positions_far_outside_the_canvas_are_rejected(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithBackground($manager);
        $field = TemplateField::factory()->for($template, 'template')->create();

        $payload = $this->layoutPayload($template, [
            'fields' => [[
                'id' => $field->id,
                'position' => ['x' => 99999, 'y' => 50, 'width' => 100, 'height' => 20],
            ]],
        ]);

        $response = $this->actingAs($manager)
            ->post("/admin/templates/{$template->id}/designer", ['layout_json' => json_encode($payload)]);

        $response->assertSessionHasErrors();
        $this->assertNull($field->fresh()->position);
    }

    public function test_field_belonging_to_a_different_template_is_rejected(): void
    {
        $manager = User::factory()->create();
        $templateA = $this->templateWithBackground($manager);
        $templateB = CertificateTemplate::factory()->for($manager, 'creator')->create();
        $foreignField = TemplateField::factory()->for($templateB, 'template')->create();

        $payload = $this->layoutPayload($templateA, [
            'fields' => [[
                'id' => $foreignField->id,
                'position' => ['x' => 10, 'y' => 10, 'width' => 100, 'height' => 20],
            ]],
        ]);

        $response = $this->actingAs($manager)
            ->post("/admin/templates/{$templateA->id}/designer", ['layout_json' => json_encode($payload)]);

        $response->assertSessionHasErrors('fields');
        $this->assertNull($foreignField->fresh()->position);
    }

    public function test_archived_template_layout_cannot_be_modified(): void
    {
        $manager = User::factory()->create();
        $template = $this->templateWithBackground($manager);
        $template->update(['status' => 'archived']);

        // Viewing stays allowed (read-only) — only saving is blocked.
        $this->actingAs($manager)->get("/admin/templates/{$template->id}/designer")->assertOk();

        $payload = $this->layoutPayload($template);
        $this->actingAs($manager)
            ->post("/admin/templates/{$template->id}/designer", ['layout_json' => json_encode($payload)])
            ->assertForbidden();

        $this->actingAs($manager)
            ->post("/admin/templates/{$template->id}/background", ['background' => $this->samplePdf()])
            ->assertForbidden();
    }

    public function test_background_upload_rejects_a_non_pdf_file(): void
    {
        $manager = User::factory()->create();
        $template = CertificateTemplate::factory()->for($manager, 'creator')->create();

        $response = $this->actingAs($manager)->post("/admin/templates/{$template->id}/background", [
            'background' => UploadedFile::fake()->create('not-a-pdf.txt', 10, 'text/plain'),
        ]);

        $response->assertSessionHasErrors('background');
        $this->assertFalse($template->fresh()->hasBackground());
    }

    public function test_background_upload_accepts_a_valid_pdf_and_streams_it_back(): void
    {
        $manager = User::factory()->create();
        $template = CertificateTemplate::factory()->for($manager, 'creator')->create();

        $this->actingAs($manager)
            ->post("/admin/templates/{$template->id}/background", ['background' => $this->samplePdf()])
            ->assertRedirect();

        $template->refresh();
        $this->assertTrue($template->hasBackground());
        $this->assertSame('sample.pdf', $template->original_filename);

        $this->actingAs($manager)
            ->get("/admin/templates/{$template->id}/background")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /** Phase 3 regression: blank slug still auto-generates (see docs/CHANGELOG.md Phase 3). */
    public function test_template_can_be_created_with_a_blank_slug_and_it_auto_generates(): void
    {
        $manager = User::factory()->create();

        $response = $this->actingAs($manager)->post('/admin/templates', [
            'name' => 'A Brand New Template',
            'slug' => '',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('certificate_templates', [
            'name' => 'A Brand New Template',
            'slug' => 'a-brand-new-template',
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function layoutPayload(CertificateTemplate $template, array $overrides = []): array
    {
        return array_merge([
            'page_width' => 842.0,
            'page_height' => 595.0,
            'fields' => [],
            'certificate_number' => null,
            'qr_code' => null,
        ], $overrides);
    }
}
