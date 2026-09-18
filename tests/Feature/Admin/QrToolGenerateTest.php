<?php

namespace Tests\Feature\Admin;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\QrCertificate;
use App\Models\QrConferenceOption;
use App\Models\QrConferenceType;
use App\Models\User;
use App\Services\Certificates\QrCodeService;
use Database\Seeders\QrCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The old-tool-parity single page (/admin/qr-tool/generate) — recreates
 * IEEEQRCODEGENERATOR-main/templates/index.html's actual layout/workflow,
 * only swapping Excel-as-storage for the database and the QR's plain-text
 * payload for a public verification URL. See docs/CERTIFICATE_SYSTEM.md
 * §Simple QR tool: old-tool-parity rebuild.
 */
class QrToolGenerateTest extends TestCase
{
    use RefreshDatabase;

    private function seedTool(): void
    {
        // QrCategorySeeder needs an existing user to own the category --
        // ensure one exists regardless of whether this particular test
        // creates its own admin/manager first.
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

    public function test_page_loads_with_zero_certificate_templates(): void
    {
        $this->assertSame(0, CertificateTemplate::count());
        $this->seedTool();
        $manager = User::factory()->create();

        $this->actingAs($manager)
            ->get('/admin/qr-tool/generate')
            ->assertOk()
            ->assertSee('Create Entry')
            ->assertSee('Generated Result')
            ->assertSee('Recent Entries')
            ->assertDontSee('No active templates')
            ->assertDontSee('Activate a template first')
            ->assertDontSee('primary category');

        $this->assertSame(0, CertificateTemplate::count());
    }

    public function test_name_is_required(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $response = $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload(['name' => '']));

        $response->assertSessionHasErrors('name');
        $this->assertSame(0, QrCertificate::count());
    }

    public function test_record_saved_to_database(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $response = $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());

        $response->assertOk()->assertSee('Dr. Hadaate Ullah')->assertSee('Keynote Speaker')->assertSee('Technical Session TS-1');

        $certificate = QrCertificate::first();
        $this->assertSame('Dr. Hadaate Ullah', $certificate->recipient_name);
        $this->assertSame('IEEE BECITHCON 2026', $certificate->event_name);
        $this->assertSame('Keynote Speaker', $certificate->data['role']);
        $this->assertSame('Technical Session TS-1', $certificate->data['session']);
    }

    public function test_session_checkbox_behavior(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        // Unchecked: no session required, none stored.
        $response = $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload([
            'include_session' => '0', 'session' => '', 'include_conference' => '0', 'conference_type' => '', 'conference_select' => '',
        ]));
        $response->assertSessionDoesntHaveErrors();
        $this->assertArrayNotHasKey('session', QrCertificate::first()->data);
    }

    public function test_codeword_is_16_char_uppercase_alphanumeric_and_unique(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload(['name' => 'First Person']));
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload(['name' => 'Second Person']));

        $codewords = QrCertificate::query()->pluck('codeword')->all();
        $this->assertCount(2, $codewords);
        $this->assertCount(2, array_unique($codewords));
        foreach ($codewords as $codeword) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{16}$/', $codeword);
        }
    }

    public function test_qr_contains_the_verification_url_and_verification_works(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());
        $certificate = QrCertificate::first();

        $url = app(QrCodeService::class)->verificationUrlForCodeword($certificate->codeword);
        $this->assertSame("/certificate/verify/{$certificate->codeword}", parse_url($url, PHP_URL_PATH));

        $this->get(parse_url($url, PHP_URL_PATH))
            ->assertOk()
            ->assertSee('Certificate Verified')
            ->assertSee('Dr. Hadaate Ullah');
    }

    public function test_duplicate_name_role_session_reuses_existing_record(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $first = $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());
        preg_match('/<strong id="codeword">([^<]+)<\/strong>/', $first->getContent(), $matches);
        $firstCodeword = $matches[1];

        $second = $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());
        preg_match('/<strong id="codeword">([^<]+)<\/strong>/', $second->getContent(), $matches);
        $secondCodeword = $matches[1];

        $this->assertSame($firstCodeword, $secondCodeword);
        $this->assertSame(1, QrCertificate::count());
        $second->assertSee('Matched an existing entry');
    }

    public function test_role_option_add_persists_and_appears_on_reload(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $response = $this->actingAs($manager)->postJson('/admin/qr-tool/options/roles/add', ['value' => 'Technical Program Chair']);
        $response->assertOk()->assertJsonFragment(['options' => [
            'Session Chair', 'Invited Speaker', 'Keynote Speaker', 'Volunteer', 'Technical Program Chair',
        ]]);

        $this->actingAs($manager)->get('/admin/qr-tool/generate')->assertSee('Technical Program Chair');
    }

    public function test_role_option_remove_does_not_alter_existing_records(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload(['role_select' => 'Volunteer']));
        $certificate = QrCertificate::first();

        $this->actingAs($manager)->postJson('/admin/qr-tool/options/roles/remove', ['value' => 'Volunteer'])
            ->assertOk()
            ->assertJsonFragment(['options' => ['Session Chair', 'Invited Speaker', 'Keynote Speaker']]);

        $certificate->refresh();
        $this->assertSame('Volunteer', $certificate->data['role']);
    }

    public function test_conference_type_and_option_add_persist(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $this->actingAs($manager)->postJson('/admin/qr-tool/options/conference-types/add', ['value' => 'Workshop'])
            ->assertOk();
        $this->assertTrue(QrConferenceType::where('name', 'Workshop')->exists());

        $this->actingAs($manager)->postJson('/admin/qr-tool/options/conference-options/add', [
            'type' => 'Workshop', 'value' => 'IEEE Student Workshop 2026',
        ])->assertOk();

        $this->assertTrue(
            QrConferenceOption::whereHas('type', fn ($q) => $q->where('name', 'Workshop'))
                ->where('name', 'IEEE Student Workshop 2026')
                ->exists()
        );
    }

    public function test_unauthorized_user_is_blocked(): void
    {
        $this->seedTool();

        $this->get('/admin/qr-tool/generate')->assertRedirect('/admin/login');
        $this->post('/admin/qr-tool/generate', [])->assertRedirect('/admin/login');

        $inactive = User::factory()->inactive()->create();
        $this->actingAs($inactive)->get('/admin/qr-tool/generate')->assertRedirect('/admin/login');
    }

    public function test_recent_entries_come_from_database(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload(['name' => 'Alice Example']));
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload(['name' => 'Bob Example']));

        $this->actingAs($manager)->get('/admin/qr-tool/generate')
            ->assertOk()->assertSee('Alice Example')->assertSee('Bob Example');
    }

    public function test_download_excel_exports_database_rows(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();
        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload(['name' => 'Alice Example']));

        $response = $this->actingAs($manager)->get('/admin/qr-tool/generate/export.xlsx');

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
    }

    public function test_advanced_certificate_system_remains_untouched(): void
    {
        $this->seedTool();
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/qr-tool/generate', $this->validPayload());

        $this->assertSame(0, CertificateTemplate::count());
        $this->assertSame(0, Certificate::count());
    }
}
