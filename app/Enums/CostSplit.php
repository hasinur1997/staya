<?php

namespace App\Enums;

/**
 * How a month's grocery expenses are divided between members.
 */
enum CostSplit: string
{
    /** Everyone who lived in the mess pays the same share. */
    case Equal = 'equal';

    /** Each member pays for the meals they had. */
    case Meals = 'meals';

    public function label(): string
    {
        return match ($this) {
            self::Equal => __('Equal share'),
            self::Meals => __('By meal count'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Equal => __('Total cost is divided equally between every member who lived in the mess.'),
            self::Meals => __('Total cost is divided by the total meals; each member pays for their own meals.'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $split): array => ['value' => $split->value, 'label' => $split->label()], self::cases());
    }
}
