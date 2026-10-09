<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditEventType;
use App\Enums\DeletableRecordType;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Deletion\DeletionRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsForms;
use Tests\TestCase;
use TMAn1An\FormBuilder\Enums\PageStatus;
use TMAn1An\FormBuilder\Models\Page;
use TMAn1An\FormBuilder\Models\PageMedia;
use TMAn1An\FormBuilder\Styling\PageStyleSchema;

/**
 * IEEE host integration of the Page Builder from tman1an/formbuilder:
 * IEEE roles (via App\FormBuilder\IeeeAuthorizer), the Logbook (via
 * LogbookAuditLogger), the IEEE admin/site layouts, and the embedded-form
 * flow. The package's own test suite covers the builder in depth; this file
 * proves the host wiring. See docs/FORM_BUILDER.md.
 */
class PageBuilderIntegrationTest extends TestCase
{
    use BuildsForms, RefreshDatabase;

    private function createPage(User $user, string $title = 'Volunteer Landing'): Page
    {
        $this->actingAs($user)->post('/admin/pages', ['title' => $title])->assertRedirect();

        return Page::query()->where('title', $title)->latest('id')->firstOrFail();
    }

    private function savePage(User $user, Page $page, array $blocks, array $overrides = [])
    {
        $fresh = $page->fresh();

        return $this->actingAs($user)->putJson("/admin/pages/{$page->id}/builder", array_replace([
            'version' => $fresh->lock_version,
            'intent' => 'save',
            'title' => $fresh->title,
            'slug' => $fresh->slug,
            'settings' => [],
            'style_settings' => PageStyleSchema::defaults(),
            'blocks' => $blocks,
        ], $overrides));
    }

    public function test_pages_are_in_the_ieee_admin_nav_and_layout(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->get('/admin')->assertOk()->assertSee('All Pages')->assertSee('Create Page')->assertSee('All Forms');
        $this->actingAs($manager)->get('/admin/pages')->assertOk()
            ->assertSee('IEEE IUBAT')            // IEEE admin sidebar
            ->assertSee('formbuilder-assets/css/admin.css', false);
    }

    public function test_certificate_manager_builds_and_publishes_but_cannot_archive_or_use_custom_css(): void
    {
        $manager = User::factory()->create();
        $page = $this->createPage($manager);

        $this->savePage($manager, $page, [['ref' => 'h', 'type' => 'heading', 'settings' => ['text' => 'Join us']]], ['intent' => 'publish'])->assertOk();
        $this->assertSame(PageStatus::Published, $page->fresh()->status);

        $this->savePage($manager, $page, [], ['custom_css' => 'body{display:none}'])->assertStatus(422)->assertJsonValidationErrors(['custom_css']);
        $this->actingAs($manager)->post("/admin/pages/{$page->id}/archive")->assertForbidden();

        $admin = User::factory()->superAdmin()->create();
        $this->savePage($admin, $page, [['ref' => 'h', 'type' => 'heading', 'settings' => ['text' => 'Join us']]], ['custom_css' => '.pb-heading{color:red}'])->assertOk();
        $this->actingAs($admin)->post("/admin/pages/{$page->id}/archive")->assertRedirect();
        $this->assertSame(PageStatus::Archived, $page->fresh()->status);
    }

    public function test_page_actions_are_recorded_in_the_logbook(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $page = $this->createPage($admin, 'Logbook Page');
        $this->savePage($admin, $page, [['ref' => 'h', 'type' => 'heading', 'settings' => ['text' => 'x']]], ['intent' => 'publish'])->assertOk();

        $events = AuditLog::query()->where('record_type', DeletableRecordType::Page)->where('record_id', $page->id)->pluck('event_type')->all();
        $this->assertContains(AuditEventType::PageCreated, $events);
        $this->assertContains(AuditEventType::PageUpdated, $events);
        $this->assertContains(AuditEventType::PagePublished, $events);
        $this->assertSame('super_admin', AuditLog::query()->where('record_type', DeletableRecordType::Page)->value('actor_role'));

        $this->actingAs($admin)->get('/admin/logbook')->assertOk()->assertSee('Page published')->assertSee('Logbook Page')->assertSee('/pages/logbook-page');
    }

    public function test_page_record_type_is_never_a_deletion_target(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $page = $this->createPage($admin);

        $this->expectException(\LogicException::class);
        app(DeletionRequestService::class)->request(DeletableRecordType::Page, $page->id, $admin, 'nope');
    }

    public function test_public_page_renders_inside_the_ieee_site_layout_with_an_embedded_form(): void
    {
        $manager = User::factory()->create();
        $form = $this->publishedForm($manager, [$this->fieldDef('text', 'full_name', 'Full name', ['required' => true])], ['name' => 'IEEE Volunteer Form']);
        $page = $this->createPage($manager, 'IEEE Volunteer Registration');
        $this->savePage($manager, $page, [
            ['ref' => 'h', 'type' => 'heading', 'settings' => ['text' => 'Volunteer with IEEE IUBAT', 'level' => 'h1']],
            ['ref' => 'f', 'type' => 'form', 'settings' => ['form_id' => $form->id]],
        ], ['intent' => 'publish'])->assertOk();
        auth()->logout();

        $this->get('/pages/ieee-volunteer-registration')->assertOk()
            ->assertSee('IEEE Volunteer Registration &mdash; '.e(config('site.site.name')), false)  // escaped title in the site <title>
            ->assertSee('IEEE Privacy Policy')        // required IEEE footer links still present
            ->assertSee('Volunteer with IEEE IUBAT')
            ->assertSee('id="ff-form-'.$form->id.'"', false);

        $this->post("/forms/{$form->slug}", ['_embed_page' => $page->id, 'full_name' => 'Rahim'])
            ->assertRedirect('/pages/ieee-volunteer-registration#ff-form-'.$form->id);
        $this->assertSame(1, $form->submissions()->count());

        $this->actingAs($manager)->get("/admin/forms/{$form->id}/submissions")->assertOk()->assertSee('Rahim');
    }

    public function test_page_images_are_stored_under_form_builder_pages_and_served_without_storage_link(): void
    {
        Storage::fake('public');
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/pages/media', ['image' => UploadedFile::fake()->image('team.jpg', 300, 200)], ['Accept' => 'application/json'])->assertCreated();
        $media = PageMedia::sole();

        $this->assertStringStartsWith('form-builder/pages/', $media->path);
        Storage::disk('public')->assertExists($media->path);
        auth()->logout();
        $this->get($media->url())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_inactive_users_cannot_reach_the_page_builder(): void
    {
        $inactive = User::factory()->inactive()->create();
        $this->actingAs($inactive)->get('/admin/pages')->assertRedirect('/admin/login');

        auth()->logout();
        $this->get('/admin/pages')->assertRedirect('/admin/login');
    }
}
