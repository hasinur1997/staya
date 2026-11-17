<?php

namespace App\Actions\Members;

use App\Enums\AuditLog;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateMember
{
    public function __construct(private OpenStay $openStay) {}

    /**
     * Register a member on someone's behalf: approved straight away, with
     * the Member role and an open stay. The account is either created in the
     * same step (`email` + `password`) or an existing user without a member
     * profile (`user_id`). Authorization happens in the form request.
     *
     * @param  array<string, string>  $details  Validated member fields, phone normalized
     * @param  array{user_id: int}|array{email: string, password: string, verified?: bool}  $account
     */
    public function handle(User $actor, array $details, array $account, string $joinedOn, UploadedFile $nidFile, ?UploadedFile $photo = null): Member
    {
        $uploads = new MemberUploads;

        try {
            $nidPath = $uploads->store($nidFile, 'nid');
            $photoPath = $uploads->store($photo, 'photos');

            [$member, $isNewUser] = DB::transaction(function () use ($actor, $details, $account, $joinedOn, $nidPath, $photoPath): array {
                $name = trim($details['first_name'].' '.$details['last_name']);
                $isNewUser = ! isset($account['user_id']);

                if (isset($account['user_id'])) {
                    $user = User::query()->lockForUpdate()->findOrFail($account['user_id']);
                    $user->forceFill(['name' => $name, 'name_bn' => ($details['name_bn'] ?? $name)])->save();
                } else {
                    $user = User::create(['name' => $name, 'name_bn' => ($details['name_bn'] ?? $name), 'email' => $account['email'], 'password' => $account['password']]);

                    if ($account['verified'] ?? false) {
                        $user->forceFill(['email_verified_at' => now()])->save();
                    }
                }

                $userBefore = $isNewUser ? null : $user->auditSnapshot();
                $user->assignRole(Role::member());

                $member = new Member([...$details, 'nid_file_path' => $nidPath, 'photo_path' => $photoPath]);
                $member->user()->associate($user);
                $member->status = MemberStatus::Active;
                $member->reviewed_by = $actor->id;
                $member->reviewed_at = now();
                $member->save();

                $this->openStay->handle($member, $joinedOn);

                activity()
                    ->useLog(AuditLog::Access)
                    ->performedOn($user)
                    ->event($isNewUser ? 'created' : 'updated')
                    ->withChanges(array_filter(['old' => $userBefore, 'attributes' => $user->auditSnapshot()]))
                    ->log($isNewUser ? 'user_created' : 'user_updated');

                activity()
                    ->useLog(AuditLog::Member)
                    ->performedOn($member)
                    ->event('created')
                    ->withChanges(['attributes' => $member->auditSnapshot()])
                    ->log('member_created');

                return [$member, $isNewUser];
            });
        } catch (Throwable $exception) {
            $uploads->discard();

            throw $exception;
        }

        if ($isNewUser && ! ($account['verified'] ?? false)) {
            event(new Registered($member->user));
        }

        return $member;
    }
}
