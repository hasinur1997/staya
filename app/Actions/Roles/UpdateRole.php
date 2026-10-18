<?php

namespace App\Actions\Roles;

use App\Enums\AuditLog;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class UpdateRole
{
    /**
     * Rename a role and/or change its permissions. System role names are
     * kept as they are.
     *
     * @param  array<int, string>|null  $permissions  Null leaves them unchanged.
     */
    public function handle(Role $role, string $name, ?array $permissions = null): Role
    {
        return DB::transaction(function () use ($role, $name, $permissions): Role {
            $before = $role->auditSnapshot();

            if (! $role->is_system) {
                $role->update(['name' => $name]);
            }

            if (! $role->isAdmin() && $permissions !== null) {
                $role->syncPermissions($permissions);
            }

            $after = $role->auditSnapshot();

            if ($before !== $after) {
                activity()
                    ->useLog(AuditLog::Access)
                    ->performedOn($role)
                    ->event('updated')
                    ->withChanges(['old' => $before, 'attributes' => $after])
                    ->log('role_updated');
            }

            return $role;
        });
    }
}
