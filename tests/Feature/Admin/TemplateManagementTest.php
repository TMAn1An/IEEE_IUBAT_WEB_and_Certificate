<?php

namespace Tests\Feature\Admin;

use App\Models\CertificateTemplate;
use App\Models\TemplateField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 required tests (see docs/CHANGELOG.md). Kept to the essentials
 * per the instruction not to build broad CRUD test suites — this is not a
 * full CRUD suite, just the core dynamic-field rules.
 */
class TemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_certificate_manager_can_create_a_template(): void
    {
        $manager = User::factory()->create(); // default factory role is certificate_manager

        $response = $this->actingAs($manager)->post('/admin/templates', [
            'name' => 'BECITHCON Speaker',
            'description' => 'Certificate for speakers',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('certificate_templates', [
            'name' => 'BECITHCON Speaker',
            'slug' => 'becithcon-speaker',
            'status' => 'draft',
            'created_by' => $manager->id,
        ]);
    }

    public function test_field_keys_must_be_unique_within_a_template(): void
    {
        $manager = User::factory()->create();
        $template = CertificateTemplate::factory()->for($manager, 'creator')->create();
        TemplateField::factory()->for($template, 'template')->create(['field_key' => 'role']);

        $response = $this->actingAs($manager)->post("/admin/templates/{$template->id}/fields", [
            'label' => 'Another Role',
            'field_key' => 'role',
            'field_type' => 'text',
        ]);

        $response->assertSessionHasErrors('field_key');
        $this->assertSame(1, TemplateField::where('certificate_template_id', $template->id)->where('field_key', 'role')->count());
    }

    public function test_dropdown_field_requires_at_least_one_option(): void
    {
        $manager = User::factory()->create();
        $template = CertificateTemplate::factory()->for($manager, 'creator')->create();

        $response = $this->actingAs($manager)->post("/admin/templates/{$template->id}/fields", [
            'label' => 'Role',
            'field_key' => 'role',
            'field_type' => 'dropdown',
        ]);

        $response->assertSessionHasErrors('options');
        $this->assertDatabaseMissing('template_fields', ['field_key' => 'role']);
    }

    public function test_only_one_recipient_name_field_is_allowed_per_template(): void
    {
        $manager = User::factory()->create();
        $template = CertificateTemplate::factory()->for($manager, 'creator')->create();
        $first = TemplateField::factory()->for($template, 'template')->create([
            'field_key' => 'name', 'field_type' => 'text', 'is_recipient_name' => true,
        ]);

        $this->actingAs($manager)->post("/admin/templates/{$template->id}/fields", [
            'label' => 'Author Name',
            'field_key' => 'author_name',
            'field_type' => 'text',
            'is_recipient_name' => '1',
        ]);

        $this->assertFalse($first->fresh()->is_recipient_name);
        $this->assertTrue(TemplateField::where('field_key', 'author_name')->first()->is_recipient_name);
        $this->assertSame(1, TemplateField::where('certificate_template_id', $template->id)->where('is_recipient_name', true)->count());
    }

    public function test_template_cannot_activate_without_exactly_one_recipient_name_field(): void
    {
        $manager = User::factory()->create();
        $template = CertificateTemplate::factory()->for($manager, 'creator')->create();
        TemplateField::factory()->for($template, 'template')->create(['field_key' => 'role', 'is_recipient_name' => false]);

        $response = $this->actingAs($manager)->post("/admin/templates/{$template->id}/activate");

        $response->assertSessionHasErrors('activation');
        $this->assertSame('draft', $template->fresh()->status->value);

        TemplateField::factory()->for($template, 'template')->create(['field_key' => 'name', 'field_type' => 'text', 'is_recipient_name' => true]);

        $this->actingAs($manager)->post("/admin/templates/{$template->id}/activate");
        $this->assertSame('active', $template->fresh()->status->value);
    }

    public function test_dynamic_form_preview_renders_fields_from_the_database(): void
    {
        $manager = User::factory()->create();
        $template = CertificateTemplate::factory()->for($manager, 'creator')->create();
        TemplateField::factory()->for($template, 'template')->create([
            'label' => 'Paper ID', 'field_key' => 'paper_id', 'field_type' => 'number', 'sort_order' => 1,
        ]);
        TemplateField::factory()->for($template, 'template')->create([
            'label' => 'Track', 'field_key' => 'track', 'field_type' => 'dropdown',
            'options' => ['Biomedical Signal Processing', 'Health Informatics'], 'sort_order' => 2,
        ]);

        $response = $this->actingAs($manager)->get("/admin/templates/{$template->id}/edit");

        $response->assertOk();
        $response->assertSee('Paper ID');
        $response->assertSee('Track');
        $response->assertSee('Biomedical Signal Processing');
        $response->assertSee('type="number"', false);
    }

    public function test_guest_and_unauthorized_access_are_blocked(): void
    {
        $this->get('/admin/templates')->assertRedirect('/admin/login');

        $manager = User::factory()->create();
        $template = CertificateTemplate::factory()->for($manager, 'creator')->create();

        // Deactivated admin: blocked the same way user-management routes are.
        $inactive = User::factory()->inactive()->create();
        $this->actingAs($inactive)->get('/admin/templates')->assertRedirect('/admin/login');
    }
}
