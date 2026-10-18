<?php

namespace App\Actions\Users;

use App\Enums\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DeactivateUser
{
    /**
     * Block the account from signing in and end its sessions. Its history
     * stays intact.
     */
    public function handle(User $actor, User $user): void
    {
        DB::transaction(function () use ($actor, $user): void {
            Role::query()->whereKey(Role::admin()->id)->lockForUpdate()->first();
            $user->refresh();

            Gate::forUser($actor)->authorize('deactivate', $user);

            $before = $user->auditSnapshot();

            $user->forceFill([
                'is_active' => false,
                'remember_token' => Str::random(60),
            ])->save();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            activity()
                ->useLog(AuditLog::Access)
                ->performedOn($user)
                ->event('updated')
                ->withChanges(['old' => $before, 'attributes' => $user->auditSnapshot()])
                ->log('user_deactivated');
        });
    }
}
