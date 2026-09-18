<?php

namespace Tests\Feature\Admin;

use App\Models\CertificateTemplate;
use App\Models\QrCategory;
use App\Models\QrCategoryField;
use App\Models\QrCertificate;
use App\Models\User;
use Database\Seeders\QrCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the simple QR tool's generate/records/category workflow is
 * fully independent of CertificateTemplate -- see the inspection notes in
 * docs/CERTIFICATE_SYSTEM.md §Simple QR tool, based directly on
 * IEEEQRCODEGENERATOR-main/app.py and templates/index.html.
 */
class QrToolGenerateTest extends TestCase
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

    public function test_seeded_category_matches_the_real_old_tool(): void
    {
        $category = $this->seededCategory();

        $this->assertSame('BECITHCON 2026', $category->name);
        $this->assertSame('IEEE BECITHCON 2026', $category->event_name);

        $role = $category->fields->firstWhere('key', 'role');
        // Exact PRESET_ROLES from app.py -- no "Other" option exists in the
        // real tool, so none is seeded here either.
        $this->assertSame(
            ['Session Chair', 'Invited Speaker', 'Keynote Speaker', 'Volunteer'],
            $role->options
        );

        $recipient = $category->fields->firstWhere('key', 'recipient_name');
        $this->assertTrue($recipient->is_recipient_name);
        $this->assertTrue($recipient->required);

        $session = $category->fields->firstWhere('key', 'session');
        $this->assertFalse($session->required); // optional in the old tool
    }

    public function test_generate_qr_works_with_zero_certificate_templates(): void
    {
        $this->assertSame(0, CertificateTemplate::count());

        $manager = User::factory()->create();
        $category = $this->seededCategory();

        $this->actingAs($manager)
            ->get("/admin/qr-tool/generate/{$category->id}")
            ->assertOk()
            ->assertDontSee('No active templates')
            ->assertDontSee('Activate a template first');

        $response = $this->actingAs($manager)->post("/admin/qr-tool/generate/{$category->id}", [
            'fields' => ['recipient_name' => 'Dr. Hadaate Ullah', 'role' => 'Keynote Speaker', 'session' => 'Technical Session TS-1'],
        ]);

        $certificate = QrCertificate::first();
        $response->assertRedirect("/admin/qr-tool/records/{$certificate->id}");
        $this->assertSame(0, CertificateTemplate::count());
    }

    public function test_old_form_fields_and_role_options_render(): void
    {
        $manager = User::factory()->create();
        $category = $this->seededCategory();

        $this->actingAs($manager)
            ->get("/admin/qr-tool/generate/{$category->id}")
            ->assertOk()
            ->assertSee('Name')
            ->assertSee('Role')
            ->assertSee('Session')
            ->assertSee('Session Chair')
            ->assertSee('Invited Speaker')
            ->assertSee('Keynote Speaker')
            ->assertSee('Volunteer')
            ->assertDontSee('Other');
    }

    public function test_recipient_event_and_session_are_saved(): void
    {
        $manager = User::factory()->create();
        $category = $this->seededCategory();

        $this->actingAs($manager)->post("/admin/qr-tool/generate/{$category->id}", [
            'fields' => ['recipient_name' => 'Dr. Hadaate Ullah', 'role' => 'Keynote Speaker', 'session' => 'Technical Session TS-1'],
        ]);

        $certificate = QrCertificate::first();
        $this->assertSame('Dr. Hadaate Ullah', $certificate->recipient_name);
        $this->assertSame('IEEE BECITHCON 2026', $certificate->event_name);
        $this->assertSame('Technical Session TS-1', $certificate->data['session']);
        $this->assertSame('Keynote Speaker', $certificate->data['role']);
    }

    public function test_session_is_optional(): void
    {
        $manager = User::factory()->create();
        $category = $this->seededCategory();

        $response = $this->actingAs($manager)->post("/admin/qr-tool/generate/{$category->id}", [
            'fields' => ['recipient_name' => 'Dr. Hadaate Ullah', 'role' => 'Volunteer'],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, QrCertificate::count());
    }

    public function test_codeword_matches_the_historical_format_and_is_unique(): void
    {
        $manager = User::factory()->create();
        $category = $this->seededCategory();

        $this->actingAs($manager)->post("/admin/qr-tool/generate/{$category->id}", [
            'fields' => ['recipient_name' => 'First Person', 'role' => 'Volunteer'],
        ]);
        $this->actingAs($manager)->post("/admin/qr-tool/generate/{$category->id}", [
            'fields' => ['recipient_name' => 'Second Person', 'role' => 'Volunteer'],
        ]);

        $codewords = QrCertificate::query()->pluck('codeword')->all();
        $this->assertCount(2, $codewords);
        $this->assertCount(2, array_unique($codewords));
        foreach ($codewords as $codeword) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{16}$/', $codeword);
        }
    }

    public function test_qr_png_endpoint_generates_a_valid_png(): void
    {
        $manager = User::factory()->create();
        $category = $this->seededCategory();
        $this->actingAs($manager)->post("/admin/qr-tool/generate/{$category->id}", [
            'fields' => ['recipient_name' => 'Dr. Hadaate Ullah', 'role' => 'Volunteer'],
        ]);
        $certificate = QrCertificate::first();

        $response = $this->actingAs($manager)->get("/admin/qr-tool/records/{$certificate->id}/qr.png");

        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG\x0d\x0a\x1a\x0a", $response->getContent());
    }

    public function test_unauthorized_user_is_blocked(): void
    {
        $category = $this->seededCategory();

        $this->get("/admin/qr-tool/generate/{$category->id}")->assertRedirect('/admin/login');
        $this->post("/admin/qr-tool/generate/{$category->id}", [])->assertRedirect('/admin/login');

        $inactive = User::factory()->inactive()->create();
        $this->actingAs($inactive)
            ->get("/admin/qr-tool/generate/{$category->id}")
            ->assertRedirect('/admin/login');
    }

    public function test_records_list_and_search_work(): void
    {
        $manager = User::factory()->create();
        $category = $this->seededCategory();
        $this->actingAs($manager)->post("/admin/qr-tool/generate/{$category->id}", [
            'fields' => ['recipient_name' => 'Alice Example', 'role' => 'Volunteer'],
        ]);
        $this->actingAs($manager)->post("/admin/qr-tool/generate/{$category->id}", [
            'fields' => ['recipient_name' => 'Bob Example', 'role' => 'Keynote Speaker'],
        ]);

        $this->actingAs($manager)->get('/admin/qr-tool/records')
            ->assertOk()->assertSee('Alice Example')->assertSee('Bob Example');

        $this->actingAs($manager)->get('/admin/qr-tool/records?search=Keynote')
            ->assertOk()->assertSee('Bob Example')->assertDontSee('Alice Example');
    }

    public function test_different_categories_support_different_schemas(): void
    {
        $manager = User::factory()->create();

        $volunteer = QrCategory::factory()->for($manager, 'creator')->create(['name' => 'Volunteer Program']);
        QrCategoryField::factory()->for($volunteer, 'category')->create([
            'key' => 'recipient_name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'is_recipient_name' => true,
        ]);
        QrCategoryField::factory()->for($volunteer, 'category')->create([
            'key' => 'department', 'label' => 'Department', 'type' => 'text', 'required' => true,
        ]);

        $paper = QrCategory::factory()->for($manager, 'creator')->create(['name' => 'Research Paper']);
        QrCategoryField::factory()->for($paper, 'category')->create([
            'key' => 'recipient_name', 'label' => 'Author Name', 'type' => 'text', 'required' => true, 'is_recipient_name' => true,
        ]);
        QrCategoryField::factory()->for($paper, 'category')->create([
            'key' => 'paper_id', 'label' => 'Paper ID', 'type' => 'text', 'required' => true,
        ]);
        QrCategoryField::factory()->for($paper, 'category')->create([
            'key' => 'paper_title', 'label' => 'Paper Title', 'type' => 'long_text', 'required' => true,
        ]);

        $this->actingAs($manager)->get("/admin/qr-tool/generate/{$volunteer->id}")
            ->assertOk()->assertSee('Department')->assertDontSee('Paper Title');

        $this->actingAs($manager)->get("/admin/qr-tool/generate/{$paper->id}")
            ->assertOk()->assertSee('Paper ID')->assertSee('Paper Title')->assertDontSee('Department');
    }

    public function test_category_crud_and_field_management(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/qr-tool/categories', [
            'name' => 'New Category', 'event_name' => 'Some Event',
        ])->assertRedirect();

        $category = QrCategory::where('name', 'New Category')->firstOrFail();
        $this->assertTrue($category->is_active);

        $this->actingAs($manager)->post("/admin/qr-tool/categories/{$category->id}/fields", [
            'label' => 'Name', 'key' => 'recipient_name', 'type' => 'text',
            'required' => '1', 'is_recipient_name' => '1', 'show_on_verification' => '1',
        ])->assertRedirect();

        $this->assertSame(1, $category->fields()->count());

        $this->actingAs($manager)->post("/admin/qr-tool/categories/{$category->id}/deactivate")->assertRedirect();
        $this->assertFalse($category->fresh()->is_active);

        // Deactivated categories don't appear in the generate-QR chooser.
        $this->actingAs($manager)->get('/admin/qr-tool/generate')->assertDontSee('New Category');
    }
}
