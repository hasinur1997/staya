<?php

namespace App\Actions\Deposits;

use App\Concerns\BusinessCalendar;
use App\Models\MessSetting;
use App\Models\MonthClosure;
use Illuminate\Validation\ValidationException;

/**
 * The date rules every deposit action re-checks inside its transaction:
 * the form request gives the user a field error first, this makes sure the
 * rule still holds when the change is written.
 */
class DepositDateGuard
{
    /**
     * Nothing dated in a closed month may change.
     *
     * @throws ValidationException
     */
    public function assertMonthOpen(string $depositDate, string $field = 'deposit_date'): void
    {
        MonthClosure::assertOpen($depositDate, $field);
    }

    /**
     * A deposit that is about to count must not be future-dated, before the
     * fund start date or in a closed month.
     *
     * @throws ValidationException
     */
    public function assertCountable(string $depositDate, string $field = 'deposit_date'): void
    {
        if ($depositDate > BusinessCalendar::today()->toDateString()) {
            throw ValidationException::withMessages([$field => __('The date cannot be in the future.')]);
        }

        $fundStartDate = MessSetting::current()->fund_start_date;

        if ($fundStartDate !== null && $depositDate < $fundStartDate->toDateString()) {
            throw ValidationException::withMessages([
                $field => __('The date cannot be before the fund start date (:date).', ['date' => $fundStartDate->format('j M Y')]),
            ]);
        }

        $this->assertMonthOpen($depositDate, $field);
    }
}
