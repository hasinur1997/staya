<?php

namespace App\Enums;

/**
 * Where a deposit stands (`deposits.status`). Only Approved counts towards
 * the fund and the member's monthly deposits.
 */
enum DepositStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Cancelled => __('Cancelled'),
            self::Reversed => __('Reversed'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
