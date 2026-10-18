<?php

namespace App\Actions\Ledger;

use App\Concerns\Money;
use App\Enums\AuditLog;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\MessSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keeps the ledger's opening-balance entry in step with the settings.
 *
 * While the ledger holds only opening-balance entries, a change reverses
 * the old entry and posts a new one dated at the fund start date. Once any
 * other activity exists, the opening balance and fund start date are
 * locked and corrections go through manual adjustments.
 */
class SyncOpeningBalance
{
    public function __construct(
        private PostLedgerEntries $postLedgerEntries,
        private ReverseLedgerEntry $reverseLedgerEntry,
    ) {}

    /**
     * Refuse a change to the opening balance or fund start date once the
     * ledger has activity. Call inside the settings transaction, before
     * saving: it takes the fund lock so no posting can slip in between the
     * check and the opening entry.
     *
     * @throws ValidationException
     */
    public function assertChangeable(MessSetting $settings): void
    {
        Fund::locked();

        $changed = array_keys(array_filter([
            'opening_balance' => $settings->isDirty('opening_balance'),
            'fund_start_date' => $settings->isDirty('fund_start_date'),
        ]));

        if ($changed === [] || ! LedgerEntry::hasActivityBeyondOpeningBalance()) {
            return;
        }

        throw ValidationException::withMessages(array_fill_keys(
            $changed,
            __('This is locked because the ledger has activity. Correct the fund with a manual adjustment.'),
        ));
    }

    /**
     * Post whatever brings the opening entry in line with the saved
     * settings. Call inside the settings transaction, after saving.
     */
    public function handle(User $actor, MessSetting $settings): void
    {
        DB::transaction(function () use ($actor, $settings): void {
            $before = Fund::locked()->balance;

            /** @var LedgerEntry|null $current */
            $current = LedgerEntry::query()
                ->where('type', LedgerEntryType::OpeningBalance)
                ->whereDoesntHave('reversedBy')
                ->latest('id')
                ->first();

            $amount = $settings->opening_balance;
            $date = $settings->fund_start_date?->toDateString();
            $wanted = bccomp(Money::numeric($amount), '0', Money::SCALE) > 0 && $date !== null;

            if ($current === null && ! $wanted) {
                return;
            }

            if ($current !== null && $wanted && $current->amount === $amount && $current->entry_date->toDateString() === $date) {
                return;
            }

            $entries = [];

            if ($current !== null) {
                $entries[] = $this->reverseLedgerEntry->reversalOf($current, __('Opening balance changed in settings.'));
            }

            if ($wanted) {
                $entries[] = [
                    'type' => LedgerEntryType::OpeningBalance,
                    'direction' => LedgerDirection::Credit,
                    'amount' => $amount,
                    'entry_date' => $date,
                ];
            }

            $posted = $this->postLedgerEntries->handle($actor, $entries, 'opening_balance', 'fund_start_date');

            activity()
                ->useLog(AuditLog::Ledger)
                ->performedOn($posted[count($posted) - 1])
                ->event('created')
                ->withChanges([
                    'old' => [
                        'opening_balance' => $current?->amount,
                        'fund_start_date' => $current?->entry_date->toDateString(),
                        'balance' => $before,
                    ],
                    'attributes' => [
                        'opening_balance' => $wanted ? $amount : null,
                        'fund_start_date' => $wanted ? $date : null,
                        'balance' => Fund::current()->balance,
                    ],
                ])
                ->log('opening_balance_posted');
        });
    }
}
