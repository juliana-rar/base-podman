<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithScreens(array $screens): User
    {
        $role = Role::create(['name' => 'Personal '.uniqid(), 'screens' => $screens]);
        $user = User::factory()->create(['role' => 'client']);
        $user->role_id = $role->id;
        $user->save();

        return $user;
    }

    public function test_staff_can_access_a_granted_screen_but_not_others(): void
    {
        $staff = $this->staffWithScreens(['empleats']);

        $this->actingAs($staff)->get('/admin/empleats')->assertOk();
        $this->actingAs($staff)->get('/admin/stock')->assertForbidden();
    }

    public function test_admin_can_access_every_screen(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/empleats')->assertOk();
        $this->actingAs($admin)->get('/admin/stock')->assertOk();
        $this->actingAs($admin)->get('/admin/usuaris')->assertOk();
    }

    public function test_client_without_role_is_redirected_from_dashboard(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)->get('/dashboard')->assertRedirect(route('reservar'));
    }

    public function test_staff_with_screens_can_open_the_dashboard(): void
    {
        $staff = $this->staffWithScreens(['empleats']);

        $this->actingAs($staff)->get('/dashboard')->assertOk();
    }

    public function test_only_admins_reach_the_users_panel(): void
    {
        $staff = $this->staffWithScreens(['empleats']);

        $this->actingAs($staff)->get('/admin/usuaris')->assertForbidden();
    }

    public function test_admin_can_change_a_user_role_and_staff_role(): void
    {
        $admin = User::factory()->admin()->create();
        $role = Role::create(['name' => 'Recepció', 'screens' => ['reserves']]);
        $user = User::factory()->create(['role' => 'client']);

        $this->actingAs($admin)
            ->put(route('admin.usuaris.update', $user), ['is_admin' => false, 'role_id' => $role->id])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame($role->id, $user->role_id);
        $this->assertFalse($user->isAdmin());
        $this->assertEqualsCanonicalizing(['reserves'], $user->accessibleScreens());
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.usuaris.update', $admin), ['is_admin' => false, 'role_id' => null])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.usuaris.destroy', $admin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_create_a_role_with_valid_screens(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.rols.store'), ['name' => 'Gestor', 'screens' => ['serveis', 'stock', 'nonexistent']])
            ->assertSessionHasErrors('screens.2');

        $this->actingAs($admin)
            ->post(route('admin.rols.store'), ['name' => 'Gestor', 'screens' => ['serveis', 'stock']])
            ->assertRedirect();

        $role = Role::where('name', 'Gestor')->firstOrFail();
        $this->assertEqualsCanonicalizing(['serveis', 'stock'], $role->screens);
    }
}
