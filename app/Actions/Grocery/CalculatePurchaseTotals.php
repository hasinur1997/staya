<?php

namespace App\Actions\Grocery;

use App\Concerns\Money;
use Illuminate\Validation\ValidationException;

/**
 * The only place purchase totals are worked out. A line total is quantity ×
 * unit price, rounded half-up to two places; the grand total is the sum of
 * the line totals. Everything is bcmath on decimal strings, and totals sent
 * by the client are never read.
 *
 * @phpstan-type ItemInput array{item_name: string, quantity: string, unit: string, unit_price: string}
 * @phpstan-type PricedItem array{item_name: string, quantity: string, unit: string, unit_price: string, line_total: numeric-string, position: int}
 */
class CalculatePurchaseTotals
{
    /** The largest amount a DECIMAL(12,2) column holds. */
    private const string MAX = '9999999999.99';

    /**
     * @param  array<int, ItemInput>  $items  Validated line items, in order.
     * @return array{items: list<PricedItem>, grand_total: numeric-string}
     *
     * @throws ValidationException
     */
    public function handle(array $items): array
    {
        $priced = [];
        $grandTotal = '0.00';

        foreach (array_values($items) as $position => $item) {
            $lineTotal = $this->lineTotal($item['quantity'], $item['unit_price']);

            if (bccomp($lineTotal, '0', Money::SCALE) <= 0) {
                throw ValidationException::withMessages([
                    "items.{$position}.unit_price" => __('This line comes to ৳ 0.00. Check the quantity and unit price.'),
                ]);
            }

            $grandTotal = bcadd($grandTotal, $lineTotal, Money::SCALE);
            $priced[] = [
                'item_name' => trim($item['item_name']),
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'unit_price' => $item['unit_price'],
                'line_total' => $lineTotal,
                'position' => $position,
            ];
        }

        if (bccomp($grandTotal, self::MAX, Money::SCALE) > 0) {
            throw ValidationException::withMessages(['items' => __('The total is too large to record.')]);
        }

        return ['items' => $priced, 'grand_total' => $grandTotal];
    }

    /**
     * Totals for a form still being filled in: a line that is not complete
     * yet has no total and is left out of the grand total.
     *
     * @param  array<int, mixed>  $items
     * @return array{line_totals: list<string|null>, grand_total: string}
     */
    public function preview(array $items): array
    {
        $lineTotals = [];
        $grandTotal = '0.00';

        foreach (array_values($items) as $item) {
            $quantity = is_array($item) ? ($item['quantity'] ?? null) : null;
            $unitPrice = is_array($item) ? ($item['unit_price'] ?? null) : null;

            $complete = is_string($quantity) && preg_match('/^\d{1,7}(\.\d{1,3})?$/', $quantity) === 1
                && is_string($unitPrice) && preg_match('/^\d{1,10}(\.\d{1,2})?$/', $unitPrice) === 1;

            $lineTotal = $complete ? $this->lineTotal($quantity, $unitPrice) : null;
            $lineTotals[] = $lineTotal;

            if ($lineTotal !== null) {
                $grandTotal = bcadd($grandTotal, $lineTotal, Money::SCALE);
            }
        }

        return ['line_totals' => $lineTotals, 'grand_total' => $grandTotal];
    }

    /**
     * @return numeric-string
     */
    private function lineTotal(string $quantity, string $unitPrice): string
    {
        $exact = bcmul(Money::numeric($quantity), Money::numeric($unitPrice), 5);

        // Half-up for the positive amounts a purchase holds.
        return bcadd($exact, '0.005', Money::SCALE);
    }
}
