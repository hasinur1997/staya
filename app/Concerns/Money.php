<?php

namespace App\Concerns;

use InvalidArgumentException;

/**
 * Money helpers for decimal strings at scale 2. Never casts to float.
 */
class Money
{
    public const int SCALE = 2;

    /**
     * Formats "12450.5" as "৳ 12,450.50" (and "-980" as "−৳ 980.00").
     */
    public static function format(string $amount): string
    {
        $normalized = bcadd(self::numeric($amount), '0', self::SCALE);
        $negative = str_starts_with($normalized, '-');
        [$whole, $fraction] = explode('.', ltrim($normalized, '-'));
        $grouped = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);

        return ($negative ? '−' : '').'৳ '.$grouped.'.'.$fraction;
    }

    /**
     * The amount as a bcmath operand, refusing anything that is not a plain
     * decimal string (no exponents, no spaces).
     *
     * @return numeric-string
     */
    public static function numeric(string $amount): string
    {
        if (! is_numeric($amount) || preg_match('/^-?\d+(\.\d+)?$/', $amount) !== 1) {
            throw new InvalidArgumentException("[{$amount}] is not a money amount.");
        }

        return $amount;
    }
}
