<?php

namespace Database\Factories;

use App\Models\Fund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * There is only ever one fund row, inserted by its migration; prefer
 * `Fund::current()`. Change the balance only by posting ledger entries.
 *
 * @extends Factory<Fund>
 */
class FundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 1,
            'balance' => '0.00',
        ];
    }
}
