<?php

namespace App\Actions\Ledger;

use App\Enums\AuditLog;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PostAdjustment
{
    public function __construct(private PostLedgerEntries $postLedgerEntries) {}

    /**
     * Post a manual adjustment and log it with the balance before and after.
     *
     * @param  array{direction: LedgerDirection, amount: string, entry_date: string, reason: string}  $adjustment
     */
    public function handle(User $actor, array $adjustment): LedgerEntry
    {
        return DB::transaction(function () use ($actor, $adjustment): LedgerEntry {
            $before = Fund::locked()->balance;

            $entry = $this->postLedgerEntries->handle($actor, [[
                'type' => LedgerEntryType::Adjustment,
                'direction' => $adjustment['direction'],
                'amount' => $adjustment['amount'],
                'entry_date' => $adjustment['entry_date'],
                'reason' => $adjustment['reason'],
            ]])[0];

            activity()
                ->useLog(AuditLog::Ledger)
                ->performedOn($entry)
                ->event('created')
                ->withChanges([
                    'old' => ['balance' => $before],
                    'attributes' => [...$entry->auditSnapshot(), 'balance' => Fund::current()->balance],
                ])
                ->log('adjustment_posted');

            return $entry;
        });
    }
}
