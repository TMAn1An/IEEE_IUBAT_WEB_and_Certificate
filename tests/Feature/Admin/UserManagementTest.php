<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 required tests 4-6 (see docs/CHANGELOG.md). Kept to the essentials
 * per the instruction not to build broad CRUD test suites.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_manager_cannot_access_user_management(): void
    {
        $manager = User::factory()->create(); // default factory role is certificate_manager

        $this->actingAs($manager)->get('/admin/users')->assertForbidden();
        $this->actingAs($manager)->get('/admin/users/create')->assertForbidden();
    }

    public function test_super_admin_can_access_user_management(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $otherAdmin = User::factory()->create();

        $this->actingAs($superAdmin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee($otherAdmin->email);
    }

    public function test_last_active_super_admin_cannot_be_deactivated(): void
    {
        $onlySuperAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($onlySuperAdmin)
            ->from('/admin/users')
            ->patch(route('admin.users.toggle-active', $onlySuperAdmin));

        $response->assertSessionHasErrors();
        $this->assertTrue($onlySuperAdmin->fresh()->is_active);
    }

    public function test_last_active_super_admin_role_cannot_be_changed_away(): void
    {
        $onlySuperAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($onlySuperAdmin)->put(route('admin.users.update', $onlySuperAdmin), [
            'name' => $onlySuperAdmin->name,
            'email' => $onlySuperAdmin->email,
            'role' => 'certificate_manager',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertEquals('super_admin', $onlySuperAdmin->fresh()->role->value);
    }

    public function test_a_second_active_super_admin_can_be_deactivated(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $anotherSuperAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->patch(route('admin.users.toggle-active', $anotherSuperAdmin));

        $this->assertFalse($anotherSuperAdmin->fresh()->is_active);
    }

    public function test_admin_user_passwords_are_never_exposed_in_responses(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($superAdmin)->get('/admin/users');

        $response->assertOk();
        $response->assertDontSee($target->password, false);
    }
}
