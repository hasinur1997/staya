<?php

namespace App\Actions\Ledger;

use App\Concerns\BusinessCalendar;
use App\Models\MessSetting;
use App\Models\MonthClosure;
use Illuminate\Validation\ValidationException;

/**
 * The date rules every financial action re-checks inside its transaction:
 * the form request gives the user a field error first, this makes sure the
 * rule still holds when the change is written.
 */
class BusinessDateGuard
{
    /**
     * Nothing dated in a closed month may change.
     *
     * @throws ValidationException
     */
    public function assertMonthOpen(string $date, string $field): void
    {
        MonthClosure::assertOpen($date, $field);
    }

    /**
     * A record that is about to count must not be future-dated, before the
     * fund start date or in a closed month.
     *
     * @throws ValidationException
     */
    public function assertCountable(string $date, string $field): void
    {
        if ($date > BusinessCalendar::today()->toDateString()) {
            throw ValidationException::withMessages([$field => __('The date cannot be in the future.')]);
        }

        $fundStartDate = MessSetting::current()->fund_start_date;

        if ($fundStartDate !== null && $date < $fundStartDate->toDateString()) {
            throw ValidationException::withMessages([
                $field => __('The date cannot be before the fund start date (:date).', ['date' => $fundStartDate->format('j M Y')]),
            ]);
        }

        $this->assertMonthOpen($date, $field);
    }
}
