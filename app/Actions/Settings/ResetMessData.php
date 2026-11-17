<?php

namespace App\Actions\Settings;

use App\Actions\Grocery\ReceiptUploads;
use App\Enums\AuditLog;
use App\Models\Fund;
use App\Models\MessSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Erases every financial and meal record so the mess can start again from
 * zero. This is the one deliberate exception to "never delete financial
 * records" and to the append-only ledger: the rows go through the query
 * builder, bypassing the model guards, in a single transaction, and the
 * audit log (which is never touched) records who did it, why, and how much
 * was erased. Members, users, roles, meal types, invitations and the other
 * settings are kept.
 */
class ResetMessData
{
    /** The phrase the requester must type to confirm. */
    public const string CONFIRMATION_PHRASE = 'RESET';

    /**
     * Child tables first, so no foreign key is ever left dangling.
     *
     * @var list<string>
     */
    private const array TABLES = [
        'buyer_payables',
        'grocery_purchase_items',
        'grocery_purchases',
        'deposits',
        'ledger_entries',
        'month_closures',
        'meals',
    ];

    public function __construct(private ReceiptUploads $receipts) {}

    /**
     * @return array<string, int> Rows erased, by table
     */
    public function handle(User $actor, string $reason): array
    {
        $receiptPaths = [];
        $erased = [];

        DB::transaction(function () use ($actor, $reason, &$receiptPaths, &$erased): void {
            $fund = Fund::locked();
            $settings = MessSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();

            $balanceBefore = $fund->balance;
            $settingsBefore = $settings->auditSnapshot();

            $receiptPaths = DB::table('grocery_purchases')->whereNotNull('receipt_path')->pluck('receipt_path')->all();

            foreach (self::TABLES as $table) {
                $erased[$table] = DB::table($table)->delete();
            }

            $fund->forceFill(['balance' => '0.00'])->save();

            $settings->forceFill(['opening_balance' => '0.00', 'fund_start_date' => null, 'updated_by' => $actor->id])->save();

            activity()
                ->useLog(AuditLog::Settings)
                ->performedOn($settings)
                ->event('updated')
                ->withProperties(['reason' => $reason, 'erased' => $erased, 'fund_balance_before' => $balanceBefore])
                ->withChanges([
                    'old' => array_intersect_key($settingsBefore, array_flip(['opening_balance', 'fund_start_date'])),
                    'attributes' => ['opening_balance' => '0.00', 'fund_start_date' => null],
                ])
                ->log('data_reset');
        });

        foreach ($receiptPaths as $path) {
            $this->receipts->delete($path);
        }

        return $erased;
    }
}
