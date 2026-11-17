<?php

namespace App\Enums;

/**
 * Whether a grocery purchase counts (`grocery_purchases.status`). A voided
 * purchase is hidden from lists and its ledger effect is reversed.
 */
enum GroceryPurchaseStatus: string
{
    case Active = 'active';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Voided => __('Voided'),
        };
    }
}
