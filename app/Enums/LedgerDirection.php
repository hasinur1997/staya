<?php

namespace App\Enums;

/**
 * The sign of a ledger entry: a credit adds to the fund, a debit takes from it.
 */
enum LedgerDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function opposite(): self
    {
        return match ($this) {
            self::Credit => self::Debit,
            self::Debit => self::Credit,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Credit => __('Credit'),
            self::Debit => __('Debit'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $direction) => ['value' => $direction->value, 'label' => $direction->label()], self::cases());
    }
}
