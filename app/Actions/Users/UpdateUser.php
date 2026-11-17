<?php

namespace App\Actions\Users;

use App\Enums\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateUser
{
    /**
     * Update an account and, when given, its roles.
     *
     * @param  array<int, int>|null  $roleIds
     */
    public function handle(User $actor, User $user, string $name, string $nameBn, string $email, ?array $roleIds = null): User
    {
        return DB::transaction(function () use ($actor, $user, $name, $nameBn, $email, $roleIds): User {
            // Serialize changes that could remove an Admin, then re-check the
            // rules against the locked state.
            Role::query()->whereKey(Role::admin()->id)->lockForUpdate()->first();
            $user->refresh();

            $roleIds ??= $user->roles()->pluck('id')->all();
            Gate::forUser($actor)->authorize('assignRoles', [$user, $roleIds]);

            $before = $user->auditSnapshot();

            $user->fill(['name' => $name, 'name_bn' => $nameBn, 'email' => $email]);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();
            $user->syncRoles($roleIds);

            $after = $user->auditSnapshot();

            if ($before !== $after) {
                activity()
                    ->useLog(AuditLog::Access)
                    ->performedOn($user)
                    ->event('updated')
                    ->withChanges(['old' => $before, 'attributes' => $after])
                    ->log('user_updated');
            }

            return $user;
        });
    }
}
