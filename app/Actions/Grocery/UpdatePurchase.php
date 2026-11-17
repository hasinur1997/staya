<?php

namespace App\Actions\Grocery;

use App\Actions\Ledger\BusinessDateGuard;
use App\Concerns\Money;
use App\Enums\AuditLog;
use App\Enums\MemberStatus;
use App\Models\Fund;
use App\Models\GroceryPurchase;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @phpstan-import-type ItemInput from CalculatePurchaseTotals
 */
class UpdatePurchase
{
    public function __construct(
        private BusinessDateGuard $dateGuard,
        private CalculatePurchaseTotals $calculateTotals,
        private SettlePurchase $settlePurchase,
        private ReceiptUploads $receipts,
    ) {}

    /**
     * Edit a purchase: replace its items, recompute the totals and settle
     * the difference with the fund and the payable, all in one transaction.
     * The edit window is re-checked under the row lock. Locks the purchase,
     * then its payable, then the fund.
     *
     * @param  array{purchase_date: string, notes: string|null, items: array<int, ItemInput>}  $data
     * @param  Member|null  $buyer  A different buyer, or null to keep the current one.
     */
    public function handle(User $actor, GroceryPurchase $purchase, array $data, ?Member $buyer = null, ?UploadedFile $receipt = null, bool $removeReceipt = false): GroceryPurchase
    {
        $newReceiptPath = $this->receipts->store($receipt);

        try {
            [$purchase, $staleReceiptPath] = DB::transaction(function () use ($actor, $purchase, $data, $buyer, $newReceiptPath, $removeReceipt): array {
                $purchase = GroceryPurchase::query()->lockForUpdate()->findOrFail($purchase->id);

                Gate::forUser($actor)->authorize('update', $purchase);

                $this->dateGuard->assertMonthOpen($purchase->purchase_date->toDateString(), 'purchase_date');
                $this->dateGuard->assertCountable($data['purchase_date'], 'purchase_date');

                if ($buyer !== null && $buyer->id !== $purchase->buyer_member_id) {
                    $this->assertBuyerCanChange($actor, $purchase, $buyer);
                    $purchase->buyer_member_id = $buyer->id;
                }

                $before = $purchase->auditSnapshot();
                $balanceBefore = Fund::locked()->balance;
                $totals = $this->calculateTotals->handle($data['items']);

                $staleReceiptPath = null;

                if ($newReceiptPath !== null || $removeReceipt) {
                    $staleReceiptPath = $purchase->receipt_path;
                    $purchase->receipt_path = $newReceiptPath;
                }

                $purchase->fill([
                    'purchase_date' => $data['purchase_date'],
                    'grand_total' => $totals['grand_total'],
                    'notes' => $data['notes'],
                    'updated_by' => $actor->id,
                ])->save();

                $purchase->items()->delete();
                $purchase->items()->createMany($totals['items']);

                $this->settlePurchase->handle($actor, $purchase, $totals['grand_total'], $data['purchase_date'], __('Purchase edited'));

                activity()
                    ->useLog(AuditLog::Grocery)
                    ->performedOn($purchase)
                    ->event('updated')
                    ->withChanges([
                        'old' => [...$before, 'balance' => $balanceBefore],
                        'attributes' => [...$purchase->refresh()->auditSnapshot(), 'balance' => Fund::current()->balance],
                    ])
                    ->log('purchase_updated');

                return [$purchase, $staleReceiptPath];
            });
        } catch (Throwable $e) {
            $this->receipts->delete($newReceiptPath);

            throw $e;
        }

        $this->receipts->delete($staleReceiptPath);

        return $purchase;
    }

    /**
     * The buyer changes only for staff who may record for others, to an
     * active member, and not once the old buyer has been handed money.
     */
    private function assertBuyerCanChange(User $actor, GroceryPurchase $purchase, Member $buyer): void
    {
        Gate::forUser($actor)->authorize('createForOthers', GroceryPurchase::class);

        if ($buyer->status !== MemberStatus::Active) {
            throw ValidationException::withMessages([
                'buyer_member_id' => __('Only an active member can be the buyer.'),
            ]);
        }

        $reimbursed = Money::numeric($purchase->payable()->first()->amount_reimbursed ?? '0.00');

        if (bccomp($reimbursed, '0', Money::SCALE) > 0) {
            throw ValidationException::withMessages([
                'buyer_member_id' => __('The buyer has already been reimbursed for this purchase. Void it and record a new one instead.'),
            ]);
        }
    }
}
