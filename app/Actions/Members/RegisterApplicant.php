<?php

namespace App\Actions\Members;

use App\Enums\AuditLog;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class RegisterApplicant
{
    /**
     * Create the account and a Pending member for a public sign-up. The
     * account has no roles, and the registration gate keeps it out of the
     * app until someone with `member.approve_registration` decides.
     *
     * @param  array<string, string>  $details  Validated member fields, phone normalized
     */
    public function handle(array $details, string $email, string $password, UploadedFile $nidFile, ?UploadedFile $photo = null): User
    {
        $uploads = new MemberUploads;

        try {
            $nidPath = $uploads->store($nidFile, 'nid');
            $photoPath = $uploads->store($photo, 'photos');

            return DB::transaction(function () use ($details, $email, $password, $nidPath, $photoPath): User {
                $user = User::create([
                    'name' => trim($details['first_name'].' '.$details['last_name']),
                    'name_bn' => $details['name_bn'],
                    'email' => $email,
                    'password' => $password,
                ]);

                $member = new Member([...$details, 'nid_file_path' => $nidPath, 'photo_path' => $photoPath]);
                $member->user()->associate($user);
                $member->status = MemberStatus::Pending;
                $member->save();

                activity()
                    ->useLog(AuditLog::Member)
                    ->causedBy($user)
                    ->performedOn($member)
                    ->event('created')
                    ->withChanges(['attributes' => $member->auditSnapshot()])
                    ->log('registration_submitted');

                return $user;
            });
        } catch (Throwable $exception) {
            $uploads->discard();

            throw $exception;
        }
    }
}
