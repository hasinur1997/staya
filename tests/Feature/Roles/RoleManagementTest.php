<?php

namespace Tests\Feature\Roles;

use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    public function test_role_list_requires_role_view()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('roles.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->withPermissions(Permission::RoleView)->create())
            ->get(route('roles.index'))
            ->assertOk()
            // The three system roles plus the viewer's own throwaway role.
            ->assertInertia(fn (Assert $page) => $page
                ->component('roles/index')
                ->has('roles', 4)
                ->where('roles.0.is_system', true)
                ->where('can.create', false));
    }

    public function test_a_role_created_in_the_ui_grants_exactly_its_permissions()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('roles.store'), [
                'name' => 'Auditor',
                'permissions' => ['user.view'],
            ])
            ->assertSessionHasNoErrors();

        $auditor = User::factory()->create();
        $this->actingAs($admin)
            ->put(route('users.update', $auditor), [
                'name' => $auditor->name,
                'email' => $auditor->email,
                'roles' => [Role::findByName('Auditor')->id],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($auditor->fresh());

        $this->get(route('users.index'))->assertOk();
        $this->get(route('users.create'))->assertForbidden();
        $this->get(route('roles.index'))->assertForbidden();
        $this->post(route('roles.store'), ['name' => 'Mine', 'permissions' => []])->assertForbidden();
    }

    public function test_creating_a_role_requires_role_manage()
    {
        $this->actingAs(User::factory()->withPermissions(Permission::RoleView)->create())
            ->post(route('roles.store'), ['name' => 'Cooks', 'permissions' => []])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'Cooks']);
    }

    public function test_a_user_cannot_grant_a_permission_they_lack()
    {
        $actor = User::factory()->withPermissions(Permission::RoleManage, Permission::UserView)->create();

        $this->actingAs($actor)
            ->post(route('roles.store'), [
                'name' => 'Cooks',
                'permissions' => ['user.view', 'ledger.adjust'],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'Cooks']);

        $role = Role::factory()->withPermissions(Permission::UserView)->create();

        $this->actingAs($actor)
            ->put(route('roles.update', $role), [
                'name' => $role->name,
                'permissions' => ['user.view', 'ledger.adjust'],
            ])
            ->assertForbidden();

        $this->assertFalse($role->fresh()->hasPermissionTo('ledger.adjust'));
    }

    public function test_a_user_can_remove_a_permission_they_lack_from_a_role()
    {
        $actor = User::factory()->withPermissions(Permission::RoleManage)->create();
        $role = Role::factory()->withPermissions(Permission::LedgerAdjust, Permission::GroceryView)->create();

        $this->actingAs($actor)
            ->put(route('roles.update', $role), [
                'name' => $role->name,
                'permissions' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $role->fresh()->permissions);
    }

    public function test_a_user_cannot_change_the_permissions_of_a_role_they_hold()
    {
        $actor = User::factory()->withPermissions(Permission::RoleManage, Permission::UserView)->create();
        $role = $actor->roles()->sole();

        $this->actingAs($actor)
            ->put(route('roles.update', $role), [
                'name' => $role->name,
                'permissions' => ['role.manage'],
            ])
            ->assertForbidden();

        $this->assertTrue($role->fresh()->hasPermissionTo('user.view'));
    }

    public function test_the_admin_role_keeps_every_permission()
    {
        $admin = User::factory()->admin()->create();
        $actor = User::factory()->withPermissions(...Permission::cases())->create();

        $this->actingAs($actor)
            ->put(route('roles.update', Role::admin()), [
                'name' => 'Admin',
                'permissions' => ['grocery.view'],
            ])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->can('role.manage'));
    }

    public function test_system_roles_cannot_be_renamed()
    {
        $manager = Role::findByName('Manager');

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('roles.update', $manager), ['name' => 'Boss'])
            ->assertForbidden();

        $this->assertSame('Manager', $manager->fresh()->name);
    }

    public function test_custom_roles_can_be_renamed_and_the_change_is_logged()
    {
        $role = Role::factory()->withPermissions(Permission::GroceryView)->create(['name' => 'Cooks']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('roles.update', $role), [
                'name' => 'Kitchen',
                'permissions' => ['grocery.view', 'grocery.create'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Kitchen', $role->fresh()->name);

        $changes = Activity::query()->where('description', 'role_updated')->sole()->attribute_changes;
        $this->assertSame(['name' => 'Cooks', 'permissions' => ['grocery.view']], $changes['old']);
        $this->assertSame(['name' => 'Kitchen', 'permissions' => ['grocery.create', 'grocery.view']], $changes['attributes']);
    }

    public function test_system_roles_cannot_be_deleted()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('roles.destroy', Role::findByName('Member')))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => 'Member']);
    }

    public function test_a_role_in_use_cannot_be_deleted()
    {
        $role = Role::factory()->create();
        User::factory()->create()->assignRole($role);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('roles.destroy', $role))
            ->assertForbidden();

        $this->assertModelExists($role);
    }

    public function test_an_unused_custom_role_can_be_deleted_and_is_logged()
    {
        $role = Role::factory()->withPermissions(Permission::GroceryView)->create(['name' => 'Cooks']);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertModelMissing($role);

        $activity = Activity::query()->where('description', 'role_deleted')->sole();
        $this->assertSame('role', $activity->subject_type);
        $this->assertSame($role->id, $activity->subject_id);
        $this->assertSame(['name' => 'Cooks', 'permissions' => ['grocery.view']], $activity->attribute_changes['old']);
    }

    public function test_deleting_a_role_requires_role_manage()
    {
        $role = Role::factory()->create();

        $this->actingAs(User::factory()->withPermissions(Permission::RoleView)->create())
            ->delete(route('roles.destroy', $role))
            ->assertForbidden();

        $this->assertModelExists($role);
    }
}
