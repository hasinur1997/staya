<?php

namespace App\Concerns;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

trait MoneyRules
{
    /**
     * Rules for a money amount sent as a plain decimal string that fits
     * DECIMAL(12,2). It is never parsed as a float.
     *
     * @return array<int, ValidationRule|Closure|array<mixed>|string>
     */
    protected function moneyRules(bool $positive = false): array
    {
        return [
            'required',
            'string',
            'regex:/^\d{1,10}(\.\d{1,2})?$/',
            ...($positive ? [$this->greaterThanZeroRule()] : []),
        ];
    }

    /**
     * Messages for the money rules on the given attribute.
     *
     * @return array<string, string>
     */
    protected function moneyMessages(string $attribute, bool $positive = false): array
    {
        return [
            $attribute.'.regex' => $positive
                ? __('Enter an amount greater than zero with at most two decimal places.')
                : __('Enter an amount of zero or more with at most two decimal places.'),
        ];
    }

    private function greaterThanZeroRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_numeric($value) && is_string($value) && preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value) === 1 && bccomp($value, '0', Money::SCALE) <= 0) {
                $fail(__('Enter an amount greater than zero with at most two decimal places.'));
            }
        };
    }
}
