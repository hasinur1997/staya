<?php

use App\Enums\Permission;
use App\Models\Role;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The seeder only gives Manager and Member their defaults when the role
     * is first created, so existing installs get the meal permissions here.
     */
    public function up(): void
    {
        (new AccessControlSeeder)->run();

        $grants = [
            'Member' => [Permission::MealViewOwn],
            'Manager' => [Permission::MealViewOwn, Permission::MealViewAll, Permission::MealRecord],
        ];

        foreach ($grants as $role => $permissions) {
            Role::query()->where('name', $role)->first()?->givePermissionTo(array_map(fn (Permission $permission): string => $permission->value, $permissions));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void {}
};
