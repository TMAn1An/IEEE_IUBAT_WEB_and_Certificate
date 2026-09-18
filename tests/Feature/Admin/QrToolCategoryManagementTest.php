<?php

namespace Tests\Feature\Admin;

use App\Models\QrCategory;
use App\Models\QrCategoryField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The multi-category management screens (/admin/qr-tool/categories) --
 * kept separate from the old-tool-parity single page
 * (/admin/qr-tool/generate, tested in QrToolGenerateTest), which is
 * deliberately bound to one fixed category rather than exposing this CRUD.
 * See docs/CERTIFICATE_SYSTEM.md §Simple QR tool.
 */
class QrToolCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

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

        $this->actingAs($manager)->get("/admin/qr-tool/categories/{$volunteer->id}/edit")
            ->assertOk()->assertSee('Department')->assertDontSee('Paper ID');

        $this->actingAs($manager)->get("/admin/qr-tool/categories/{$paper->id}/edit")
            ->assertOk()->assertSee('Paper ID')->assertDontSee('Department');
    }
}
