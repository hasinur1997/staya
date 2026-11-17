<?php

namespace App\Enums;

/**
 * Who paid for a grocery purchase (`grocery_purchases.paid_by`). The fund
 * pays straight away; a member who paid from their own pocket is owed the
 * whole total by the fund.
 */
enum GroceryPaidBy: string
{
    case Fund = 'fund';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Fund => __('Fund'),
            self::Member => __('Member'),
        };
    }
}
