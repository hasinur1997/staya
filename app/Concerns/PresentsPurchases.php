<?php

namespace App\Concerns;

use App\Models\BuyerPayable;
use App\Models\GroceryPurchase;
use App\Models\GroceryPurchaseItem;
use App\Models\User;
use App\Policies\GroceryPurchasePolicy;

/**
 * How a grocery purchase is sent to the frontend: money and quantities as
 * decimal strings, the date as a Y-m-d business date, and what the viewer
 * may do with it. Expects `buyer` and `items` to be loaded.
 */
trait PresentsPurchases
{
    /**
     * @return array<string, mixed>
     */
    protected function purchaseRow(GroceryPurchase $purchase, User $viewer): array
    {
        $canUpdate = $viewer->can('update', $purchase);
        $canDelete = $viewer->can('delete', $purchase);
        $ownWindowOpen = $purchase->isOwnedBy($viewer) && ! $purchase->isVoided() && $purchase->isWithinEditWindow();

        return [
            'id' => $purchase->id,
            'purchase_date' => $purchase->purchase_date->toDateString(),
            'buyer_member_id' => $purchase->buyer_member_id,
            'buyer_name' => $purchase->buyer->full_name,
            'grand_total' => $purchase->grand_total,
            'item_count' => $purchase->items->count(),
            'item_names' => $purchase->items->take(3)->pluck('item_name')->all(),
            'status' => $purchase->status->value,
            'status_label' => $purchase->status->label(),
            'is_own' => $purchase->isOwnedBy($viewer),
            'locked' => app(GroceryPurchasePolicy::class)->isLockedFor($viewer, $purchase),
            // Set while the viewer's own edit window is running (ISO 8601, UTC).
            'window_closes_at' => $ownWindowOpen && ($canUpdate || $canDelete) && ! $viewer->canAny(['grocery.update_any', 'grocery.delete_any'])
                ? $purchase->editWindowClosesAt()->toIso8601String()
                : null,
            'can' => [
                'update' => $canUpdate,
                'delete' => $canDelete,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function purchaseItems(GroceryPurchase $purchase): array
    {
        return $purchase->items->map(fn (GroceryPurchaseItem $item) => [
            'id' => $item->id,
            'item_name' => $item->item_name,
            'quantity' => $item->quantity,
            'unit' => $item->unit->value,
            'unit_label' => $item->unit->label(),
            'unit_price' => $item->unit_price,
            'line_total' => $item->line_total,
        ])->values()->all();
    }

    /**
     * Expects `purchase.buyer` to be loaded.
     *
     * @return array<string, mixed>
     */
    protected function payableRow(BuyerPayable $payable, User $viewer): array
    {
        return [
            'id' => $payable->id,
            'grocery_purchase_id' => $payable->grocery_purchase_id,
            'purchase_date' => $payable->purchase->purchase_date->toDateString(),
            'member_id' => $payable->member_id,
            'member_name' => $payable->purchase->buyer->full_name,
            'amount' => $payable->amount,
            'amount_reimbursed' => $payable->amount_reimbursed,
            'outstanding' => $payable->outstanding(),
            'can' => [
                'reimburse' => $viewer->can('reimburse', $payable),
            ],
        ];
    }
}
