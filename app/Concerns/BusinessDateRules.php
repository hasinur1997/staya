<?php

namespace App\Concerns;

use App\Exceptions\MonthClosed;
use App\Models\MessSetting;
use App\Models\MonthClosure;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

trait BusinessDateRules
{
    /**
     * Rules for a business date (Y-m-d): not later than today in
     * Asia/Dhaka, not before the fund start date once it is set, and not in
     * a closed month.
     *
     * @return array<int, ValidationRule|Closure|array<mixed>|string>
     */
    protected function businessDateRules(): array
    {
        $fundStartDate = MessSetting::current()->fund_start_date;

        return [
            'required',
            ...$this->notInTheFutureRules(),
            ...($fundStartDate ? ['after_or_equal:'.$fundStartDate->toDateString()] : []),
            $this->notInClosedMonthRule(),
        ];
    }

    /**
     * Fails when the date falls in a closed month. Actions check again
     * inside their transaction; this gives the form a field error first.
     */
    protected function notInClosedMonthRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1 || ! strtotime($value)) {
                return;
            }

            if (MonthClosure::isClosed($value)) {
                $fail(MonthClosed::message(MonthClosure::monthOf($value)));
            }
        };
    }

    /**
     * Rules for a Y-m-d date that is not later than today in Asia/Dhaka.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function notInTheFutureRules(): array
    {
        return [
            'date_format:Y-m-d',
            'before_or_equal:'.BusinessCalendar::today()->toDateString(),
        ];
    }

    /**
     * Messages for the date rules on the given attribute.
     *
     * @return array<string, string>
     */
    protected function businessDateMessages(string $attribute): array
    {
        $fundStartDate = MessSetting::current()->fund_start_date;

        return [
            $attribute.'.before_or_equal' => __('The date cannot be in the future.'),
            $attribute.'.after_or_equal' => __('The date cannot be before the fund start date (:date).', [
                'date' => $fundStartDate?->format('j M Y') ?? '',
            ]),
        ];
    }
}
