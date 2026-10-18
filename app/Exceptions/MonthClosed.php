<?php

namespace App\Exceptions;

use DateTimeInterface;
use Illuminate\Validation\ValidationException;

/**
 * Something dated in a closed month was about to be created or changed.
 */
class MonthClosed extends ValidationException
{
    public static function for(DateTimeInterface $month, string $field = 'entry_date'): self
    {
        return static::withMessages([
            $field => self::message($month),
        ]);
    }

    public static function message(DateTimeInterface $month): string
    {
        return __(':month is closed. Reopen it before changing anything dated in it.', [
            'month' => $month->format('F Y'),
        ]);
    }
}
