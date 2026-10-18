<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionKey;
use App\Enums\SystemRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Syncs the permission catalog and the three system roles. Safe to re-run:
 * Admin is always re-synced to the whole catalog, while Manager and Member
 * get their defaults only when first created, so later edits are kept.
 */
class AccessControlSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        foreach (PermissionKey::cases() as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission->value, 'guard_name' => $guard],
                ['group' => $permission->group(), 'description' => $permission->description()],
            );
        }

        Permission::query()
            ->where('guard_name', $guard)
            ->whereNotIn('name', PermissionKey::values())
            ->delete();

        foreach (SystemRole::cases() as $systemRole) {
            $role = Role::query()->firstOrCreate(
                ['name' => $systemRole->value, 'guard_name' => $guard],
                ['is_system' => true],
            );

            if (! $role->is_system) {
                $role->forceFill(['is_system' => true])->save();
            }

            if ($systemRole === SystemRole::Admin || $role->wasRecentlyCreated) {
                $role->syncPermissions(array_map(
                    fn (PermissionKey $permission): string => $permission->value,
                    $systemRole->defaultPermissions(),
                ));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
