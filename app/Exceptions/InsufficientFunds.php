<?php

namespace App\Exceptions;

use App\Concerns\Money;
use Illuminate\Validation\ValidationException;

/**
 * A posting would take the fund below ৳0. Rendered as a validation error
 * that says how much is missing.
 */
class InsufficientFunds extends ValidationException
{
    /**
     * The shortfall at scale 2, e.g. "50.50".
     */
    public string $missing = '0.00';

    public static function short(string $missing, string $field = 'amount'): self
    {
        $exception = static::withMessages([
            $field => __('The fund does not have enough money. :amount is missing.', ['amount' => Money::format($missing)]),
        ]);
        $exception->missing = $missing;

        return $exception;
    }
}
