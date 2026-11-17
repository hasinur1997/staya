<?php

namespace App\Actions\Members;

use App\Enums\AuditLog;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateMember
{
    /**
     * Update a member's details and keep the account's name and email in
     * sync. A changed email must be verified again. New files replace the
     * old ones, which are deleted once the change is committed.
     *
     * @param  array<string, string>  $details  Validated member fields, phone normalized
     */
    public function handle(Member $member, array $details, string $email, ?UploadedFile $nidFile = null, ?UploadedFile $photo = null, ?UploadedFile $banner = null, bool $removeBanner = false): Member
    {
        $uploads = new MemberUploads;

        try {
            $nidPath = $uploads->store($nidFile, 'nid');
            $photoPath = $uploads->store($photo, 'photos');
            $bannerPath = $uploads->store($banner, 'banners');

            $replaced = DB::transaction(function () use ($member, $details, $email, $nidPath, $photoPath, $bannerPath, $removeBanner): array {
                $member = Member::query()->lockForUpdate()->findOrFail($member->id);
                $before = $member->auditSnapshot();
                $replaced = [];

                $member->fill($details);
                $nidChanged = $member->isDirty('nid_number');

                if ($nidPath !== null) {
                    $replaced[] = $member->nid_file_path;
                    $member->nid_file_path = $nidPath;
                }

                if ($photoPath !== null) {
                    $replaced[] = $member->photo_path;
                    $member->photo_path = $photoPath;
                }

                if ($bannerPath !== null || $removeBanner) {
                    $replaced[] = $member->banner_path;
                    $member->banner_path = $bannerPath;
                }

                $member->save();

                $user = $member->user;
                $user->fill(['name' => $member->full_name, 'email' => $email]);

                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                }

                $user->save();

                $after = $member->auditSnapshot();

                if ($before !== $after || $nidChanged || $nidPath !== null || $photoPath !== null || $bannerPath !== null || $removeBanner) {
                    activity()
                        ->useLog(AuditLog::Member)
                        ->performedOn($member)
                        ->event('updated')
                        ->withChanges(['old' => $before, 'attributes' => $after])
                        ->withProperties([
                            'nid_number_changed' => $nidChanged,
                            'nid_file_replaced' => $nidPath !== null,
                            'photo_replaced' => $photoPath !== null,
                            'banner_replaced' => $bannerPath !== null || $removeBanner,
                        ])
                        ->log('member_updated');
                }

                return $replaced;
            });
        } catch (Throwable $exception) {
            $uploads->discard();

            throw $exception;
        }

        MemberUploads::delete(...$replaced);

        return $member->refresh();
    }
}
