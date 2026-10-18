<?php

namespace Database\Factories;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Inserts rows without touching the fund balance. Tests that need a real
 * balance must post through PostLedgerEntries instead.
 *
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_date' => now('Asia/Dhaka')->toDateString(),
            'type' => LedgerEntryType::Adjustment,
            'direction' => LedgerDirection::Credit,
            'amount' => fake()->numberBetween(100, 500000).'.'.fake()->numerify('##'),
            'reason' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function credit(): static
    {
        return $this->state(fn (array $attributes) => ['direction' => LedgerDirection::Credit]);
    }

    public function debit(): static
    {
        return $this->state(fn (array $attributes) => ['direction' => LedgerDirection::Debit]);
    }

    public function openingBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => LedgerEntryType::OpeningBalance,
            'direction' => LedgerDirection::Credit,
            'reason' => null,
        ]);
    }
}
