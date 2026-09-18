<?php

namespace Tests\Feature\Admin;

use App\Enums\CertificateTemplateStatus;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\TemplateField;
use App\Models\User;
use App\Services\Certificates\SimpleCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 required tests (see docs/CHANGELOG.md) for the simplified,
 * no-PDF "Generate QR" workflow — App\Services\Certificates\
 * SimpleCertificateService + App\Http\Controllers\Admin\CertificateQrController.
 */
class CertificateQrGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function activeTemplate(User $creator): CertificateTemplate
    {
        $template = CertificateTemplate::factory()->for($creator, 'creator')->create();

        TemplateField::factory()->for($template, 'template')->create([
            'field_key' => 'recipient_name',
            'label' => 'Name',
            'field_type' => 'text',
            'is_required' => true,
            'is_recipient_name' => true,
        ]);

        TemplateField::factory()->for($template, 'template')->create([
            'field_key' => 'role',
            'label' => 'Role',
            'field_type' => 'text',
            'is_required' => true,
        ]);

        $template->update(['status' => CertificateTemplateStatus::Active]);

        return $template->fresh(['fields']);
    }

    public function test_authorized_manager_can_open_qr_generator_form(): void
    {
        $manager = User::factory()->create();
        $template = $this->activeTemplate($manager);

        $this->actingAs($manager)
            ->get("/admin/certificates/generate-qr/{$template->id}")
            ->assertOk()
            ->assertSee('Role');
    }

    public function test_unauthorized_user_is_blocked(): void
    {
        $manager = User::factory()->create();
        $template = $this->activeTemplate($manager);

        $this->get("/admin/certificates/generate-qr/{$template->id}")->assertRedirect('/admin/login');
        $this->post("/admin/certificates/generate-qr/{$template->id}", [])->assertRedirect('/admin/login');

        $inactive = User::factory()->inactive()->create();
        $this->actingAs($inactive)
            ->get("/admin/certificates/generate-qr/{$template->id}")
            ->assertRedirect('/admin/login');
    }

    public function test_required_field_validation(): void
    {
        $manager = User::factory()->create();
        $template = $this->activeTemplate($manager);

        $this->actingAs($manager)
            ->post("/admin/certificates/generate-qr/{$template->id}", ['fields' => ['role' => 'Speaker']])
            ->assertSessionHasErrors('fields.recipient_name');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_generating_stores_recipient_name_and_data_json_no_pdf(): void
    {
        $manager = User::factory()->create();
        $template = $this->activeTemplate($manager);

        $response = $this->actingAs($manager)->post("/admin/certificates/generate-qr/{$template->id}", [
            'fields' => ['recipient_name' => 'Jane Doe', 'role' => 'Speaker'],
        ]);

        $certificate = Certificate::first();
        $response->assertRedirect("/admin/certificates/{$certificate->id}");

        $this->assertSame('Jane Doe', $certificate->recipient_name);
        $this->assertSame('Jane Doe', $certificate->data['recipient_name']);
        $this->assertSame('Speaker', $certificate->data['role']);
        $this->assertNull($certificate->pdf_path);
        $this->assertNull($certificate->template_snapshot);
        $this->assertNull($certificate->layout_snapshot);
    }

    public function test_codeword_is_generated_and_unique(): void
    {
        $manager = User::factory()->create();
        $template = $this->activeTemplate($manager);

        $this->actingAs($manager)->post("/admin/certificates/generate-qr/{$template->id}", [
            'fields' => ['recipient_name' => 'First', 'role' => 'Speaker'],
        ]);
        $this->actingAs($manager)->post("/admin/certificates/generate-qr/{$template->id}", [
            'fields' => ['recipient_name' => 'Second', 'role' => 'Speaker'],
        ]);

        $codewords = Certificate::query()->pluck('codeword')->all();
        $this->assertCount(2, $codewords);
        $this->assertCount(2, array_unique($codewords));
        foreach ($codewords as $codeword) {
            $this->assertSame(64, strlen($codeword));
        }
    }

    public function test_draft_and_archived_templates_cannot_generate(): void
    {
        $manager = User::factory()->create();
        $template = $this->activeTemplate($manager);
        $template->update(['status' => CertificateTemplateStatus::Draft]);

        $this->actingAs($manager)->post("/admin/certificates/generate-qr/{$template->id}", [
            'fields' => ['recipient_name' => 'Jane Doe', 'role' => 'Speaker'],
        ])->assertSessionHasErrors('template');

        $template->update(['status' => CertificateTemplateStatus::Archived]);
        $this->actingAs($manager)->post("/admin/certificates/generate-qr/{$template->id}", [
            'fields' => ['recipient_name' => 'Jane Doe', 'role' => 'Speaker'],
        ])->assertSessionHasErrors('template');

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_qr_image_endpoint_generates_a_valid_png(): void
    {
        $manager = User::factory()->create();
        $template = $this->activeTemplate($manager);
        // Issued via the service directly (not an authenticated HTTP POST)
        // so the guest request below is genuinely unauthenticated --
        // actingAs() persists its user for every subsequent request in the
        // same test method.
        $certificate = app(SimpleCertificateService::class)->issue(
            $template,
            ['recipient_name' => 'Jane Doe', 'role' => 'Speaker'],
            $manager
        );

        $this->get("/admin/certificates/{$certificate->id}/qr.png")->assertRedirect('/admin/login');

        $response = $this->actingAs($manager)->get("/admin/certificates/{$certificate->id}/qr.png");
        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG\x0d\x0a\x1a\x0a", $response->getContent());
    }

    public function test_certificate_show_page_displays_qr_and_no_pdf_section_when_absent(): void
    {
        $manager = User::factory()->create();
        $template = $this->activeTemplate($manager);
        $this->actingAs($manager)->post("/admin/certificates/generate-qr/{$template->id}", [
            'fields' => ['recipient_name' => 'Jane Doe', 'role' => 'Speaker'],
        ]);
        $certificate = Certificate::first();

        $response = $this->actingAs($manager)->get("/admin/certificates/{$certificate->id}");
        $response->assertOk()
            ->assertSee('Verification QR')
            ->assertDontSee('Download PDF');
    }
}
