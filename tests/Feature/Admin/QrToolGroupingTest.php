<?php

namespace Tests\Feature\Admin;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\QrCertificate;
use App\Models\QrGroup;
use App\Models\User;
use Database\Seeders\QrCategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Automatic QR grouping -- restores the old tool's behavior of separating
 * records into one Excel file per Event Type + Event Name + Role
 * combination, as a database concept (QrGroup) instead. The admin never
 * visits a category/group page before generating a QR; the group is
 * resolved automatically. See docs/CERTIFICATE_SYSTEM.md §Simple QR tool:
 * automatic grouping.
 */
class QrToolGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function seedTool(): void
    {
        if (User::query()->doesntExist()) {
            User::factory()->create();
        }
        $this->seed(QrCategorySeeder::class);
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'include_conference' => '1',
            'conference_type' => 'Conference',
            'conference_select' => 'IEEE BECITHCON 2026',
            'role_select' => 'Keynote Speaker',
            'name' => 'Dr. Hadaate Ullah',
            'include_session' => '1',
            'session' => 'Technical Session TS-1',
        ], $overrides);
    }

    public function test_no_group_needs_to_exist_before_generating_the_first_qr(): void
    {
        $this->seedTool();
        $this->assertSame(0, QrGroup::count());
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload())->assertOk();

        $this->assertSame(1, QrGroup::count());
    }

    public function test_first_generation_auto_creates_a_group_matching_event_type_event_name_role(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload())->assertOk();

        $group = QrGroup::first();
        $this->assertSame('Conference', $group->event_type);
        $this->assertSame('IEEE BECITHCON 2026', $group->event_name);
        $this->assertSame('Keynote Speaker', $group->role);

        $certificate = QrCertificate::first();
        $this->assertSame($group->id, $certificate->qr_group_id);
    }

    public function test_same_event_type_event_name_and_role_reuses_the_same_group(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload(['name' => 'Person One']));
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload(['name' => 'Person Two']));

        $this->assertSame(1, QrGroup::count());
        $this->assertSame(2, QrGroup::first()->certificates()->count());
    }

    public function test_a_different_role_creates_a_different_group(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload(['role_select' => 'Keynote Speaker']));
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload(['role_select' => 'Session Chair', 'name' => 'Someone Else']));

        $this->assertSame(2, QrGroup::count());
    }

    public function test_a_different_event_name_creates_a_different_group(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $this->actingAs($manager)->postJson('/admin/qr-tool/options/conference-options/add', [
            'type' => 'Conference', 'value' => 'IEEE BECITHCON 2027',
        ])->assertOk();

        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload([
            'conference_select' => 'IEEE BECITHCON 2027', 'name' => 'Someone Else',
        ]));

        $this->assertSame(2, QrGroup::count());
    }

    public function test_a_different_event_type_creates_a_different_group(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload([
            'conference_type' => 'Event', 'conference_select' => 'BECITHCON 2026', 'name' => 'Someone Else',
        ]));

        $this->assertSame(2, QrGroup::count());
    }

    public function test_leaving_conference_unchecked_still_produces_a_stable_group(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload([
            'include_conference' => '0', 'conference_type' => '', 'conference_select' => '', 'name' => 'Person One',
        ]))->assertOk();
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload([
            'include_conference' => '0', 'conference_type' => '', 'conference_select' => '', 'name' => 'Person Two',
        ]))->assertOk();

        $this->assertSame(1, QrGroup::count());
        $this->assertSame('No Conference', QrGroup::first()->event_type);
    }

    public function test_group_key_uniqueness_is_enforced_at_the_database_level(): void
    {
        $this->seedTool();
        $group = QrGroup::factory()->create(['event_type' => 'Conference', 'event_name' => 'IEEE BECITHCON 2026', 'role' => 'Keynote Speaker']);

        $this->expectException(QueryException::class);
        QrGroup::create([
            'event_type' => 'Conference',
            'event_name' => 'IEEE BECITHCON 2026',
            'role' => 'Keynote Speaker',
            'group_key' => $group->group_key,
            'is_active' => true,
        ]);
    }

    public function test_recent_entries_result_panel_shows_the_group_and_its_record_count(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $response = $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());

        $response->assertOk()
            ->assertSee('Saved under')
            ->assertSee('Conference / IEEE BECITHCON 2026 / Keynote Speaker')
            ->assertSee('Records in this group');
    }

    public function test_groups_index_page_lists_groups_with_record_counts(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());

        $group = QrGroup::first();

        $this->actingAs($manager)->get('/admin/qr-tool/groups')
            ->assertOk()
            ->assertSee('IEEE BECITHCON 2026')
            ->assertSee('Keynote Speaker');

        $this->actingAs($manager)->get("/admin/qr-tool/groups/{$group->id}")
            ->assertOk()
            ->assertSee('Dr. Hadaate Ullah');
    }

    public function test_group_excel_export_uses_the_old_tools_filename_convention(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());
        $group = QrGroup::first();

        $response = $this->actingAs($manager)->get("/admin/qr-tool/groups/{$group->id}/export.xlsx");

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString(
            'Conference_IEEE_BECITHCON_2026_Role_Keynote_Speaker.xlsx',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_duplicate_within_a_group_is_still_matched_on_name_and_session_only(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $first = $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());
        preg_match('/<strong id="codeword">([^<]+)<\/strong>/', $first->getContent(), $matches);
        $firstCodeword = $matches[1];

        $second = $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());
        preg_match('/<strong id="codeword">([^<]+)<\/strong>/', $second->getContent(), $matches);

        $this->assertSame($firstCodeword, $matches[1]);
        $this->assertSame(1, QrCertificate::count());
    }

    public function test_same_person_with_a_different_role_is_not_a_duplicate(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload(['name' => 'SANIM', 'role_select' => 'Session Chair']));
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload(['name' => 'SANIM', 'role_select' => 'Keynote Speaker']));

        $this->assertSame(2, QrCertificate::count());
        $this->assertSame(2, QrGroup::count());
    }

    public function test_verification_still_works_after_grouping_and_shows_event_type(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());
        $certificate = QrCertificate::first();

        $this->get("/certificate/verify/{$certificate->codeword}")
            ->assertOk()
            ->assertSee('Certificate Verified')
            ->assertSee('Event Type')
            ->assertSee('Conference')
            ->assertSee('IEEE BECITHCON 2026');
    }

    public function test_advanced_certificate_system_is_unaffected_by_grouping(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());

        $this->assertSame(0, CertificateTemplate::count());
        $this->assertSame(0, Certificate::count());
    }
}
