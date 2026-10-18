<?php

namespace App\Actions\Roles;

use App\Enums\AuditLog;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class CreateRole
{
    /**
     * Create a custom role with the chosen permissions.
     *
     * @param  array<int, string>  $permissions
     */
    public function handle(string $name, array $permissions): Role
    {
        return DB::transaction(function () use ($name, $permissions): Role {
            $role = Role::query()->create(['name' => $name, 'is_system' => false]);
            $role->syncPermissions($permissions);

            activity()
                ->useLog(AuditLog::Access)
                ->performedOn($role)
                ->event('created')
                ->withChanges(['attributes' => $role->auditSnapshot()])
                ->log('role_created');

            return $role;
        });
    }
}
