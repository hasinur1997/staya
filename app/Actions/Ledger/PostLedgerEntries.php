<?php

namespace App\Actions\Ledger;

use App\Concerns\Money;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Exceptions\InsufficientFunds;
use App\Exceptions\LedgerViolation;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only way money moves. Locks the fund row, checks every entry's month
 * is open and that the fund stays at or above ৳0, inserts the entries and
 * updates the cached balance, all in one transaction. Call it from inside
 * the caller's own transaction so the financial change and its entries
 * commit together.
 *
 * @phpstan-type PendingEntry array{
 *     type: LedgerEntryType,
 *     direction: LedgerDirection,
 *     amount: string,
 *     entry_date: string,
 *     reason?: string|null,
 *     source?: Model|null,
 *     reverses?: LedgerEntry|null,
 * }
 */
class PostLedgerEntries
{
    /**
     * Post the entries as one batch. The floor is checked against the
     * balance after the whole batch, so an edit can post a reversal and its
     * replacement together.
     *
     * @param  list<PendingEntry>  $entries
     * @param  string  $amountField  Field that carries the "missing amount" error.
     * @param  string  $dateField  Field that carries the closed-month error.
     * @return list<LedgerEntry>
     *
     * @throws InsufficientFunds
     */
    public function handle(User $actor, array $entries, string $amountField = 'amount', string $dateField = 'entry_date'): array
    {
        return DB::transaction(function () use ($actor, $entries, $amountField, $dateField): array {
            $fund = Fund::locked();

            /** @var numeric-string $balance */
            $balance = $fund->balance;

            foreach ($entries as $entry) {
                $this->assertPostable($entry, $dateField);

                $amount = Money::numeric($entry['amount']);
                $balance = $entry['direction'] === LedgerDirection::Credit
                    ? bcadd($balance, $amount, Money::SCALE)
                    : bcsub($balance, $amount, Money::SCALE);
            }

            if (bccomp($balance, '0', Money::SCALE) < 0) {
                throw InsufficientFunds::short(bcsub('0', $balance, Money::SCALE), $amountField);
            }

            $posted = array_map(fn (array $entry): LedgerEntry => LedgerEntry::query()->create([
                'entry_date' => $entry['entry_date'],
                'type' => $entry['type'],
                'direction' => $entry['direction'],
                'amount' => bcadd(Money::numeric($entry['amount']), '0', Money::SCALE),
                'source_type' => isset($entry['source']) ? $entry['source']->getMorphClass() : null,
                'source_id' => isset($entry['source']) ? $entry['source']->getKey() : null,
                'reverses_entry_id' => isset($entry['reverses']) ? $entry['reverses']->id : null,
                'reason' => $entry['reason'] ?? null,
                'created_by' => $actor->id,
            ]), $entries);

            $fund->balance = $balance;
            $fund->save();

            return $posted;
        });
    }

    /**
     * @param  PendingEntry  $entry
     */
    private function assertPostable(array $entry, string $dateField): void
    {
        if (bccomp(Money::numeric($entry['amount']), '0', Money::SCALE) <= 0) {
            throw LedgerViolation::nonPositiveAmount($entry['amount']);
        }

        $reversed = $entry['reverses'] ?? null;

        if ($reversed !== null) {
            if ($reversed->type === LedgerEntryType::Reversal) {
                throw LedgerViolation::reversalOfReversal($reversed->id);
            }

            // Checked under the fund lock, so two requests cannot both pass.
            if (LedgerEntry::query()->where('reverses_entry_id', $reversed->id)->exists()) {
                throw LedgerViolation::alreadyReversed($reversed->id);
            }
        }

        MonthClosure::assertOpen($entry['entry_date'], $dateField);
    }
}
