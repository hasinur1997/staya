<?php

namespace Database\Factories;

use App\Enums\GroceryUnit;
use App\Models\GroceryPurchase;
use App\Models\GroceryPurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroceryPurchaseItem>
 */
class GroceryPurchaseItemFactory extends Factory
{
    /**
     * Define the model's default state: one piece of something.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(20, 500).'.00';

        return [
            'grocery_purchase_id' => GroceryPurchase::factory(),
            'item_name' => fake()->randomElement(['Rice', 'Lentils', 'Onion', 'Potato', 'Soybean oil', 'Eggs', 'Salt']),
            'quantity' => '1.000',
            'unit' => GroceryUnit::Pcs,
            'unit_price' => $price,
            'line_total' => $price,
            'position' => 0,
        ];
    }
}
