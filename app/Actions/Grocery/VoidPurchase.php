<?php

namespace App\Actions\Grocery;

use App\Actions\Ledger\BusinessDateGuard;
use App\Enums\AuditLog;
use App\Enums\GroceryPurchaseStatus;
use App\Models\Fund;
use App\Models\GroceryPurchase;
use App\Models\User;
use App\Policies\GroceryPurchasePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VoidPurchase
{
    public const string OWNER_REASON = 'Deleted by owner';

    public function __construct(
        private BusinessDateGuard $dateGuard,
        private SettlePurchase $settlePurchase,
        private GroceryPurchasePolicy $policy,
    ) {}

    /**
     * "Delete" a purchase: mark it voided and undo its money in the same
     * transaction. The expense is reversed, the unpaid payable is dropped
     * and anything already reimbursed goes back to the fund. The row and
     * its items stay. An owner inside the edit window gives no reason;
     * anyone else must.
     */
    public function handle(User $actor, GroceryPurchase $purchase, ?string $reason = null): GroceryPurchase
    {
        return DB::transaction(function () use ($actor, $purchase, $reason): GroceryPurchase {
            $purchase = GroceryPurchase::query()->lockForUpdate()->findOrFail($purchase->id);

            Gate::forUser($actor)->authorize('delete', $purchase);

            $reason = trim((string) $reason);

            if ($reason === '') {
                if ($this->policy->voidNeedsReason($actor, $purchase)) {
                    throw ValidationException::withMessages(['reason' => __('Give a reason for voiding this purchase.')]);
                }

                $reason = self::OWNER_REASON;
            }

            $purchaseDate = $purchase->purchase_date->toDateString();
            $this->dateGuard->assertMonthOpen($purchaseDate, 'reason');

            $before = $purchase->auditSnapshot();
            $balanceBefore = Fund::locked()->balance;

            $this->settlePurchase->handle($actor, $purchase, '0', $purchaseDate, $reason, field: 'reason');

            $purchase->status = GroceryPurchaseStatus::Voided;
            $purchase->void_reason = $reason;
            $purchase->voided_by = $actor->id;
            $purchase->voided_at = now();
            $purchase->save();

            activity()
                ->useLog(AuditLog::Grocery)
                ->performedOn($purchase)
                ->event('updated')
                ->withChanges([
                    'old' => [...$before, 'balance' => $balanceBefore],
                    'attributes' => [...$purchase->refresh()->auditSnapshot(), 'balance' => Fund::current()->balance],
                ])
                ->log('purchase_voided');

            return $purchase;
        });
    }
}
