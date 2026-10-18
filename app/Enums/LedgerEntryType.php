<?php

namespace App\Enums;

/**
 * What a ledger entry records (`ledger_entries.type`).
 */
enum LedgerEntryType: string
{
    case OpeningBalance = 'opening_balance';
    case Deposit = 'deposit';
    case GroceryExpense = 'grocery_expense';
    case Reversal = 'reversal';
    case Adjustment = 'adjustment';
    case Reimbursement = 'reimbursement';

    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => __('Opening balance'),
            self::Deposit => __('Deposit'),
            self::GroceryExpense => __('Grocery expense'),
            self::Reversal => __('Reversal'),
            self::Adjustment => __('Adjustment'),
            self::Reimbursement => __('Reimbursement'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
