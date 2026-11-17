<?php

namespace App\Http\Controllers\Grocery;

use App\Actions\Grocery\CalculatePurchaseTotals;
use App\Http\Controllers\Controller;
use App\Models\GroceryPurchase;
use App\Models\GroceryPurchaseItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * JSON helpers for the purchase form. Neither changes anything.
 */
class PurchaseToolsController extends Controller
{
    /**
     * Item names used before that start with what was typed. There is no
     * item catalog: the names come from earlier purchases.
     */
    public function itemNames(Request $request): JsonResponse
    {
        Gate::authorize('draft', GroceryPurchase::class);

        $search = $request->string('q')->trim()->limit(100, '')->toString();

        $names = GroceryPurchaseItem::query()
            ->when($search !== '', fn ($query) => $query->where('item_name', 'like', addcslashes($search, '%_\\').'%'))
            ->select('item_name')
            ->distinct()
            ->orderBy('item_name')
            ->limit(10)
            ->pluck('item_name');

        return response()->json(['names' => $names]);
    }

    /**
     * Line totals and the grand total for the items typed so far, so the
     * form shows the server's figures instead of computing its own.
     */
    public function totals(Request $request, CalculatePurchaseTotals $calculateTotals): JsonResponse
    {
        Gate::authorize('draft', GroceryPurchase::class);

        $items = $request->input('items');

        return response()->json($calculateTotals->preview(is_array($items) ? array_slice($items, 0, 100) : []));
    }
}
