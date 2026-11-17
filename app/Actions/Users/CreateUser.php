<?php

namespace App\Actions\Users;

use App\Enums\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

class CreateUser
{
    /**
     * Create an account and assign its roles. The new user verifies their
     * email like a self-registered one.
     *
     * @param  array<int, int>  $roleIds
     */
    public function handle(string $name, string $nameBn, string $email, string $password, array $roleIds): User
    {
        $user = DB::transaction(function () use ($name, $nameBn, $email, $password, $roleIds): User {
            $user = User::create([
                'name' => $name,
                'name_bn' => $nameBn,
                'email' => $email,
                'password' => $password,
            ]);

            $user->syncRoles($roleIds);

            activity()
                ->useLog(AuditLog::Access)
                ->performedOn($user)
                ->event('created')
                ->withChanges(['attributes' => $user->auditSnapshot()])
                ->log('user_created');

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
