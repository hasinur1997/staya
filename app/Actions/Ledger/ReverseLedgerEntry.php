<?php

namespace App\Actions\Ledger;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;

/**
 * Cancels the effect of an entry by posting its opposite. The reversal
 * keeps the original entry's date, so it lands in the same reporting month
 * (and is refused while that month is closed).
 *
 * @phpstan-import-type PendingEntry from PostLedgerEntries
 */
class ReverseLedgerEntry
{
    public function __construct(private PostLedgerEntries $postLedgerEntries) {}

    /**
     * Post the reversal on its own.
     */
    public function handle(User $actor, LedgerEntry $entry, string $reason): LedgerEntry
    {
        return $this->postLedgerEntries->handle($actor, [$this->reversalOf($entry, $reason)])[0];
    }

    /**
     * The pending reversal, for callers that post it together with other
     * entries (e.g. an edit: reverse the old amount, post the new one).
     *
     * @return PendingEntry
     */
    public function reversalOf(LedgerEntry $entry, string $reason): array
    {
        return [
            'type' => LedgerEntryType::Reversal,
            'direction' => $entry->direction->opposite(),
            'amount' => $entry->amount,
            'entry_date' => $entry->entry_date->toDateString(),
            'reason' => $reason,
            'source' => $entry->source_type !== null ? $entry->source : null,
            'reverses' => $entry,
        ];
    }
}
