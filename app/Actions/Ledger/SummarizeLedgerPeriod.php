<?php

namespace App\Actions\Ledger;

use App\Concerns\Money;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;

/**
 * Fund movements for a period of business dates (inclusive).
 *
 * A reversal counts against the type of the entry it reverses, so a
 * reversed deposit lowers "deposits" instead of showing as its own line.
 * Opening-balance entries count towards the period's opening figure.
 *
 * Sums are taken in PHP with bcmath: SQLite stores DECIMAL as a float, so
 * a SQL SUM() is not exact there. The ledger is small enough at mess scale.
 */
class SummarizeLedgerPeriod
{
    /**
     * @return array{opening: string, deposits: string, expenses: string, adjustments: string, reimbursements: string, closing: string}
     */
    public function handle(string $from, string $to): array
    {
        /** @var array<string, numeric-string> $totals */
        $totals = ['opening' => '0', 'deposits' => '0', 'expenses' => '0', 'adjustments' => '0', 'reimbursements' => '0'];

        $entries = LedgerEntry::query()
            ->with('reverses:id,type')
            ->where('entry_date', '<=', $to)
            ->lazyById();

        foreach ($entries as $entry) {
            $type = $entry->type === LedgerEntryType::Reversal ? $entry->reverses?->type : $entry->type;
            $signed = $entry->signedAmount();

            $bucket = $entry->entry_date->toDateString() < $from ? 'opening' : match ($type) {
                LedgerEntryType::OpeningBalance, null, LedgerEntryType::Reversal => 'opening',
                LedgerEntryType::Deposit => 'deposits',
                LedgerEntryType::GroceryExpense => 'expenses',
                LedgerEntryType::Adjustment => 'adjustments',
                LedgerEntryType::Reimbursement => 'reimbursements',
            };

            // Expenses and reimbursements are reported as positive outflows.
            $amount = in_array($bucket, ['expenses', 'reimbursements'], true) ? bcsub('0', $signed, Money::SCALE) : $signed;
            $totals[$bucket] = bcadd($totals[$bucket], $amount, Money::SCALE);
        }

        $closing = bcsub(
            bcadd(bcadd($totals['opening'], $totals['deposits'], Money::SCALE), $totals['adjustments'], Money::SCALE),
            bcadd($totals['expenses'], $totals['reimbursements'], Money::SCALE),
            Money::SCALE,
        );

        return [
            'opening' => bcadd($totals['opening'], '0', Money::SCALE),
            'deposits' => bcadd($totals['deposits'], '0', Money::SCALE),
            'expenses' => bcadd($totals['expenses'], '0', Money::SCALE),
            'adjustments' => bcadd($totals['adjustments'], '0', Money::SCALE),
            'reimbursements' => bcadd($totals['reimbursements'], '0', Money::SCALE),
            'closing' => $closing,
        ];
    }
}
