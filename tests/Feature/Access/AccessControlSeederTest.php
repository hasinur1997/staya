<?php

namespace Tests\Feature\Access;

use App\Enums\Permission as PermissionKey;
use App\Enums\SystemRole;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class AccessControlSeederTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    public function test_every_catalog_permission_is_seeded_with_its_group()
    {
        $this->assertSame(count(PermissionKey::cases()), Permission::count());

        $this->assertDatabaseHas('permissions', [
            'name' => 'deposit.approve',
            'group' => 'deposit',
        ]);
    }

    public function test_system_roles_are_seeded_and_flagged()
    {
        foreach (SystemRole::cases() as $systemRole) {
            $this->assertTrue(Role::findByName($systemRole->value)->is_system);
        }
    }

    public function test_admin_holds_every_permission()
    {
        $this->assertEqualsCanonicalizing(
            PermissionKey::values(),
            Role::admin()->permissions()->pluck('name')->all(),
        );
    }

    public function test_member_gets_its_default_permissions()
    {
        $this->assertEqualsCanonicalizing(
            array_map(fn (PermissionKey $permission) => $permission->value, SystemRole::Member->defaultPermissions()),
            Role::findByName('Member')->permissions()->pluck('name')->all(),
        );
    }

    public function test_reseeding_keeps_edits_to_manager_and_member_but_restores_admin()
    {
        Role::findByName('Manager')->syncPermissions(['grocery.view']);
        Role::admin()->revokePermissionTo('role.manage');

        $this->seed(AccessControlSeeder::class);

        $this->assertSame(['grocery.view'], Role::findByName('Manager')->permissions()->pluck('name')->all());
        $this->assertTrue(Role::admin()->hasPermissionTo('role.manage'));
        $this->assertSame(count(PermissionKey::cases()), Permission::count());
    }
}
