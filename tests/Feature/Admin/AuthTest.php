<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 required tests 1-3 (see docs/CHANGELOG.md). Kept to the essentials
 * per the instruction not to build broad CRUD test suites.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_active_admin_can_log_in(): void
    {
        $user = User::factory()->superAdmin()->create(['password' => bcrypt('correct-password')]);

        $response = $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_admin_cannot_log_in(): void
    {
        $user = User::factory()->superAdmin()->inactive()->create(['password' => bcrypt('correct-password')]);

        $response = $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivating_an_admin_mid_session_blocks_their_next_request(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->get('/admin')->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user->fresh())->get('/admin')->assertRedirect('/admin/login');
    }
}
