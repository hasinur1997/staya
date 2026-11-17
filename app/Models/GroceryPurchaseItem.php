<?php

namespace App\Models;

use App\Enums\GroceryUnit;
use Carbon\CarbonImmutable;
use Database\Factories\GroceryPurchaseItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a grocery purchase. `line_total` is quantity × unit price,
 * computed on the server and rounded half-up to two places.
 *
 * @property int $id
 * @property int $grocery_purchase_id
 * @property string $item_name
 * @property string $quantity
 * @property GroceryUnit $unit
 * @property string $unit_price
 * @property string $line_total
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['grocery_purchase_id', 'item_name', 'quantity', 'unit', 'unit_price', 'line_total', 'position'])]
class GroceryPurchaseItem extends Model
{
    /** @use HasFactory<GroceryPurchaseItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit' => GroceryUnit::class,
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'position' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<GroceryPurchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(GroceryPurchase::class, 'grocery_purchase_id');
    }
}
