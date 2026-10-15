<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT_PASSWORD = 'Current-Pass123!';

    private const NEW_PASSWORD = 'New-Strong-Pass456#';

    public function test_guest_cannot_view_profile_page(): void
    {
        $this->get('/admin/profile')->assertRedirect('/admin/login');
    }

    public function test_guest_cannot_submit_password_change(): void
    {
        $this->put('/admin/profile/password', [
            'current_password' => 'whatever',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect('/admin/login');
    }

    public function test_super_admin_can_view_their_own_profile(): void
    {
        $user = User::factory()->superAdmin()->create(['name' => 'Alice Example']);

        $this->actingAs($user)
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('Alice Example')
            ->assertSee($user->email)
            ->assertSee('Super Admin');
    }

    public function test_certificate_manager_can_view_their_own_profile(): void
    {
        // Default factory role is certificate_manager.
        $user = User::factory()->create(['name' => 'Bob Example']);

        $this->actingAs($user)
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('Bob Example')
            ->assertSee($user->email)
            ->assertSee('Certificate Manager');
    }

    public function test_profile_page_never_exposes_the_password_hash(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::CURRENT_PASSWORD)]);

        $response = $this->actingAs($user)->get('/admin/profile');

        $response->assertOk();
        $response->assertDontSee($user->password, false);
        $response->assertDontSee(self::CURRENT_PASSWORD, false);
    }

    public function test_user_can_change_their_own_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::CURRENT_PASSWORD)]);
        $originalHash = $user->password;

        $response = $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $response->assertRedirect(route('admin.profile.edit'));
        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertNotSame($originalHash, $user->password);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));

        // Still signed in on this session — a password change must not log
        // the user themselves out.
        $this->assertAuthenticatedAs($user);

        // The new password actually works for a fresh login.
        $this->post('/admin/logout');
        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_incorrect_current_password_is_rejected_and_password_is_unchanged(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::CURRENT_PASSWORD)]);
        $originalHash = $user->password;

        $response = $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => 'totally-wrong-password',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_weak_new_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::CURRENT_PASSWORD)]);
        $originalHash = $user->password;

        $response = $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => 'short1',
            'password_confirmation' => 'short1',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::CURRENT_PASSWORD)]);
        $originalHash = $user->password;

        $response = $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_repeated_wrong_current_password_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::CURRENT_PASSWORD)]);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->put('/admin/profile/password', [
                'current_password' => 'wrong-password',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])->assertSessionHasErrors('current_password');
        }

        // The 6th attempt is throttled even with the *correct* current
        // password — the lockout is on attempts, not on being wrong again.
        $response = $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertStringContainsString(
            'Too many attempts',
            collect(session('errors')->get('current_password'))->first(),
        );
        $this->assertTrue(Hash::check(self::CURRENT_PASSWORD, $user->fresh()->password));

        RateLimiter::clear('password-update:'.$user->id);
    }

    public function test_a_successful_password_change_clears_the_rate_limiter(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::CURRENT_PASSWORD)]);

        $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => 'wrong-password',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasNoErrors();

        $this->assertFalse(RateLimiter::tooManyAttempts('password-update:'.$user->id, 5));
    }

    /**
     * There is no {user} route parameter on this endpoint at all — it can
     * only ever act on the authenticated user. This proves that directly:
     * user A changes "their" password, user B's hash is untouched no
     * matter what.
     */
    public function test_changing_password_never_affects_another_account(): void
    {
        $userA = User::factory()->create(['password' => Hash::make(self::CURRENT_PASSWORD)]);
        $userB = User::factory()->create(['password' => Hash::make('Bobs-Own-Pass789$')]);
        $userBOriginalHash = $userB->password;

        $this->actingAs($userA)->put('/admin/profile/password', [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasNoErrors();

        $this->assertSame($userBOriginalHash, $userB->fresh()->password);
        $this->assertTrue(Hash::check('Bobs-Own-Pass789$', $userB->fresh()->password));
    }

    /**
     * Even a crafted request body naming role/is_active/email cannot change
     * them — the controller writes exactly one column
     * (ProfileController::updatePassword()), so this is true regardless of
     * what a caller sends, not just because the form doesn't offer those
     * fields.
     */
    public function test_role_and_active_status_and_email_cannot_be_changed_via_the_password_endpoint(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make(self::CURRENT_PASSWORD),
            'role' => UserRole::CertificateManager,
            'is_active' => true,
            'email' => 'original@example.test',
        ]);

        $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
            'role' => 'super_admin',
            'is_active' => false,
            'email' => 'attacker-controlled@example.test',
        ])->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame(UserRole::CertificateManager, $fresh->role);
        $this->assertTrue($fresh->is_active);
        $this->assertSame('original@example.test', $fresh->email);
    }

    public function test_changing_password_invalidates_other_database_sessions(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create(['password' => Hash::make(self::CURRENT_PASSWORD)]);

        // Simulate another device already signed in: a second session row
        // for the same user that this request will not touch directly.
        $otherSessionId = Str::random(40);
        DB::table('sessions')->insert([
            'id' => $otherSessionId,
            'user_id' => $user->id,
            'ip_address' => '203.0.113.9',
            'user_agent' => 'Other Device',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => $otherSessionId]);
    }
}
