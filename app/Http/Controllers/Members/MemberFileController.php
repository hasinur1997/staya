<?php

namespace App\Http\Controllers\Members;

use App\Actions\Members\MemberUploads;
use App\Enums\AuditLog;
use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Member files live on the private `uploads` disk and leave it only
 * through these authorized routes.
 */
class MemberFileController extends Controller
{
    /**
     * Download the NID copy. Every download is audit-logged.
     */
    public function nid(Member $member): StreamedResponse
    {
        Gate::authorize('viewNid', $member);

        $disk = Storage::disk(MemberUploads::DISK);

        abort_unless($member->nid_file_path !== '' && $disk->exists($member->nid_file_path), 404);

        activity()
            ->useLog(AuditLog::Member)
            ->performedOn($member)
            ->event('nid_downloaded')
            ->log('nid_downloaded');

        $extension = pathinfo($member->nid_file_path, PATHINFO_EXTENSION);

        return $disk->download($member->nid_file_path, "nid-{$member->id}.{$extension}");
    }

    /**
     * Show the member's photo.
     */
    public function photo(Member $member): StreamedResponse
    {
        Gate::authorize('view', $member);

        $disk = Storage::disk(MemberUploads::DISK);

        abort_unless($member->photo_path !== null && $disk->exists($member->photo_path), 404);

        return $disk->response($member->photo_path, headers: ['Cache-Control' => 'private, max-age=3600']);
    }
}
