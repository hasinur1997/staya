<?php

namespace App\Enums;

/**
 * The unit a line item's quantity is measured in
 * (`grocery_purchase_items.unit`).
 */
enum GroceryUnit: string
{
    case Kg = 'kg';
    case Gm = 'gm';
    case Liter = 'liter';
    case Ml = 'ml';
    case Pcs = 'pcs';
    case Dozen = 'dozen';

    public function label(): string
    {
        return match ($this) {
            self::Kg => __('kg'),
            self::Gm => __('gm'),
            self::Liter => __('liter'),
            self::Ml => __('ml'),
            self::Pcs => __('pcs'),
            self::Dozen => __('dozen'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $unit) => ['value' => $unit->value, 'label' => $unit->label()], self::cases());
    }
}
