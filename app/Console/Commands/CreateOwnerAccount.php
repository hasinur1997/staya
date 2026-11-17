<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('staya:create-owner')]
#[Description('Create the first Admin account from the STAYA_OWNER_* environment variables')]
class CreateOwnerAccount extends Command
{
    /**
     * Create the owner only while no Admin exists, so re-running a deploy
     * never creates a second account or resets a password.
     */
    public function handle(): int
    {
        $owner = config('staya.owner');

        if (blank($owner['email']) || blank($owner['password'])) {
            $this->components->info('STAYA_OWNER_EMAIL / STAYA_OWNER_PASSWORD not set; skipping.');

            return self::SUCCESS;
        }

        $admin = Role::admin();

        if ($admin->users()->exists()) {
            $this->components->info('An Admin already exists; skipping.');

            return self::SUCCESS;
        }

        $validator = Validator::make($owner, [
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $owner['name'],
            'name_bn' => $owner['name_bn'] ?: $owner['name'],
            'email' => $owner['email'],
            'password' => $owner['password'],
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($admin);

        $this->components->info("Created the owner account {$user->email}.");

        return self::SUCCESS;
    }
}
