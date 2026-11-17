<?php

namespace App\Actions\Grocery;

use App\Actions\Ledger\BusinessDateGuard;
use App\Enums\AuditLog;
use App\Enums\GroceryPaidBy;
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
 *
 * @phpstan-type PurchaseInput array{
 *     purchase_date: string,
 *     notes: string|null,
 *     items: array<int, ItemInput>,
 *     paid_by: GroceryPaidBy,
 *     payer: Member|null,
 * }
 */
class RecordPurchase
{
    public function __construct(
        private BusinessDateGuard $dateGuard,
        private CalculatePurchaseTotals $calculateTotals,
        private SettlePurchase $settlePurchase,
        private ReceiptUploads $receipts,
    ) {}

    /**
     * Record a grocery purchase and pay for it from the fund straight
     * away. There is no approval step. The fund pays what it holds; any
     * shortfall becomes a payable owed to the buyer. When a member paid
     * from their own pocket instead, the fund is left alone and owes that
     * member the whole total.
     *
     * @param  PurchaseInput  $data
     */
    public function handle(User $actor, Member $buyer, array $data, ?UploadedFile $receipt = null): GroceryPurchase
    {
        $receiptPath = $this->receipts->store($receipt);

        try {
            return DB::transaction(function () use ($actor, $buyer, $data, $receiptPath): GroceryPurchase {
                Gate::forUser($actor)->authorize('createFor', [GroceryPurchase::class, $buyer]);

                if ($buyer->status !== MemberStatus::Active) {
                    throw ValidationException::withMessages([
                        'buyer_member_id' => __('Only an active member can be the buyer.'),
                    ]);
                }

                $payer = $data['paid_by'] === GroceryPaidBy::Member ? $data['payer'] : null;

                if ($data['paid_by'] === GroceryPaidBy::Member && $payer?->status !== MemberStatus::Active) {
                    throw ValidationException::withMessages([
                        'paid_by_member_id' => __('Choose the active member who paid.'),
                    ]);
                }

                $this->dateGuard->assertCountable($data['purchase_date'], 'purchase_date');

                $totals = $this->calculateTotals->handle($data['items']);

                $purchase = GroceryPurchase::query()->create([
                    'purchase_date' => $data['purchase_date'],
                    'buyer_member_id' => $buyer->id,
                    'grand_total' => $totals['grand_total'],
                    'paid_by' => $data['paid_by'],
                    'paid_by_member_id' => $payer?->id,
                    'notes' => $data['notes'],
                    'receipt_path' => $receiptPath,
                    'created_by' => $actor->id,
                ]);
                $purchase->items()->createMany($totals['items']);

                $balanceBefore = Fund::locked()->balance;

                $this->settlePurchase->handle($actor, $purchase, $totals['grand_total'], $data['purchase_date'], __('Purchase recorded'));

                activity()
                    ->useLog(AuditLog::Grocery)
                    ->performedOn($purchase)
                    ->event('created')
                    ->withChanges([
                        'old' => ['balance' => $balanceBefore],
                        'attributes' => [...$purchase->refresh()->auditSnapshot(), 'balance' => Fund::current()->balance],
                    ])
                    ->log('purchase_created');

                return $purchase;
            });
        } catch (Throwable $e) {
            $this->receipts->delete($receiptPath);

            throw $e;
        }
    }
}
