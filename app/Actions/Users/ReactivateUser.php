<?php

namespace App\Actions\Users;

use App\Enums\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReactivateUser
{
    /**
     * Let a deactivated account sign in again.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $before = $user->auditSnapshot();

            $user->forceFill(['is_active' => true])->save();

            activity()
                ->useLog(AuditLog::Access)
                ->performedOn($user)
                ->event('updated')
                ->withChanges(['old' => $before, 'attributes' => $user->auditSnapshot()])
                ->log('user_reactivated');
        });
    }
}
