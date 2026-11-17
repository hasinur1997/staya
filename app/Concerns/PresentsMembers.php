<?php

namespace App\Concerns;

use App\Enums\AuditLog;
use App\Models\Member;
use App\Models\MemberStay;
use App\Models\User;
use Inertia\Inertia;
use Inertia\OptionalProp;

/**
 * How a member is sent to the frontend. Props are built field by field so
 * NID data never rides along by accident: the number is an optional prop
 * the page asks for explicitly, and each reveal is audit-logged.
 */
trait PresentsMembers
{
    /**
     * Personal details shown on the profile and registration review pages.
     *
     * @return array<string, mixed>
     */
    protected function memberProfile(Member $member, User $viewer): array
    {
        $canViewNid = $viewer->can('viewNid', $member);

        return [
            'id' => $member->id,
            'user_id' => $member->user_id,
            'name' => $member->full_name,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->user->email,
            'phone' => $member->phone,
            'father_name' => $member->father_name,
            'mother_name' => $member->mother_name,
            'permanent_address' => $member->permanent_address,
            'status' => $member->status->value,
            'status_label' => $member->status->label(),
            'rejection_reason' => $member->rejection_reason,
            'reviewed_by' => $member->reviewer?->name,
            'reviewed_at' => $member->reviewed_at?->toIso8601String(),
            'registered_at' => $member->created_at?->toIso8601String(),
            'has_photo' => $member->photo_path !== null,
            'has_banner' => $member->banner_path !== null,
            'media_version' => $member->updated_at?->timestamp,
            'has_nid_file' => $canViewNid ? $member->nid_file_path !== '' : null,
            'stays' => $member->stays()->orderByDesc('joined_on')->get()->map(fn (MemberStay $stay) => [
                'id' => $stay->id,
                'joined_on' => $stay->joined_on->toDateString(),
                'left_on' => $stay->left_on?->toDateString(),
            ])->all(),
        ];
    }

    /**
     * The NID number as an optional prop (loaded with a partial reload), or
     * nothing at all for someone who may not see it.
     *
     * @return array{nid?: OptionalProp}
     */
    protected function nidProp(Member $member, User $viewer, string $context): array
    {
        if (! $viewer->can('viewNid', $member)) {
            return [];
        }

        return ['nid' => Inertia::optional(function () use ($member, $context): string {
            activity()
                ->useLog(AuditLog::Member)
                ->performedOn($member)
                ->event('nid_viewed')
                ->withProperties(['context' => $context])
                ->log('nid_viewed');

            return $member->nid_number;
        })];
    }
}
