<?php

namespace App\Actions\Members;

use App\Concerns\BusinessCalendar;
use App\Models\Member;
use App\Models\MemberStay;
use App\Models\MonthClosure;
use Illuminate\Validation\ValidationException;

class CloseStay
{
    /**
     * End the open stay on the given leave date. Call inside a transaction
     * with the member row locked. Only the open stay is touched; earlier
     * stays are never rewritten.
     *
     * @throws ValidationException
     */
    public function handle(Member $member, string $leftOn): MemberStay
    {
        $stay = $member->currentStay()->first();

        if ($stay === null) {
            throw ValidationException::withMessages([
                'left_on' => __('This member has no open stay.'),
            ]);
        }

        if ($leftOn > BusinessCalendar::today()->toDateString()) {
            throw ValidationException::withMessages([
                'left_on' => __('The date cannot be in the future.'),
            ]);
        }

        if ($leftOn < $stay->joined_on->toDateString()) {
            throw ValidationException::withMessages([
                'left_on' => __('The leave date cannot be before the stay started (:date).', [
                    'date' => $stay->joined_on->toDateString(),
                ]),
            ]);
        }

        MonthClosure::assertOpenFrom($leftOn, 'left_on');

        $stay->update(['left_on' => $leftOn]);

        return $stay;
    }
}
