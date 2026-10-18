<?php

namespace App\Actions\Members;

use App\Concerns\BusinessCalendar;
use App\Models\Member;
use App\Models\MemberStay;
use App\Models\MonthClosure;
use Illuminate\Validation\ValidationException;

class OpenStay
{
    /**
     * Start a new stay. Call inside a transaction with the member row locked.
     * A member has at most one open stay, and a new stay starts after the
     * last one ended, so stays never overlap.
     *
     * @throws ValidationException
     */
    public function handle(Member $member, string $joinedOn): MemberStay
    {
        if ($member->currentStay()->exists()) {
            throw ValidationException::withMessages([
                'joined_on' => __('This member already has an open stay.'),
            ]);
        }

        if ($joinedOn > BusinessCalendar::today()->toDateString()) {
            throw ValidationException::withMessages([
                'joined_on' => __('The date cannot be in the future.'),
            ]);
        }

        $lastLeftOn = $member->stays()->max('left_on');

        if (is_string($lastLeftOn) && $joinedOn <= substr($lastLeftOn, 0, 10)) {
            throw ValidationException::withMessages([
                'joined_on' => __('The join date must be after the last stay ended (:date).', [
                    'date' => substr($lastLeftOn, 0, 10),
                ]),
            ]);
        }

        MonthClosure::assertOpenFrom($joinedOn, 'joined_on');

        return $member->stays()->create(['joined_on' => $joinedOn]);
    }
}
