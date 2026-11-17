<?php

namespace Database\Factories;

use App\Models\BuyerPayable;
use App\Models\GroceryPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerPayable>
 */
class BuyerPayableFactory extends Factory
{
    /**
     * Define the model's default state: an open payable for the whole of a
     * purchase the fund paid nothing towards.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grocery_purchase_id' => GroceryPurchase::factory(),
            'member_id' => fn (array $attributes) => GroceryPurchase::query()->whereKey($attributes['grocery_purchase_id'])->value('buyer_member_id'),
            'amount' => fn (array $attributes) => GroceryPurchase::query()->whereKey($attributes['grocery_purchase_id'])->value('grand_total'),
            'amount_reimbursed' => '0.00',
        ];
    }
}
