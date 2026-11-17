<?php

namespace App\Enums;

/**
 * How a deposit was paid (`deposits.method`). Cash is the only method in v1.
 */
enum DepositMethod: string
{
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $method) => ['value' => $method->value, 'label' => $method->label()], self::cases());
    }
}
