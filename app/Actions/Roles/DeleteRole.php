<?php

namespace App\Actions\Roles;

use App\Enums\AuditLog;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class DeleteRole
{
    /**
     * Delete an unused custom role.
     */
    public function handle(Role $role): void
    {
        DB::transaction(function () use ($role): void {
            $before = $role->auditSnapshot();

            // The subject is gone after this, so keep its id in the entry.
            activity()
                ->useLog(AuditLog::Access)
                ->performedOn($role)
                ->event('deleted')
                ->withChanges(['old' => $before])
                ->log('role_deleted');

            $role->delete();
        });
    }
}
