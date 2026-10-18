<?php

namespace App\Concerns;

use App\Models\MessSetting;
use Illuminate\Contracts\Validation\ValidationRule;

trait BusinessDateRules
{
    /**
     * Rules for a business date (Y-m-d): not later than today in
     * Asia/Dhaka and, once it is set, not before the fund start date.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function businessDateRules(): array
    {
        $fundStartDate = MessSetting::current()->fund_start_date;

        return [
            'required',
            ...$this->notInTheFutureRules(),
            ...($fundStartDate ? ['after_or_equal:'.$fundStartDate->toDateString()] : []),
        ];
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
