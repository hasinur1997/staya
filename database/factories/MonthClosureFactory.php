<?php

namespace Database\Factories;

use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthClosure>
 */
class MonthClosureFactory extends Factory
{
    /**
     * Define the model's default state: a closed month.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'month' => fake()->unique()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-01'),
            'closed_by' => User::factory(),
            'closed_at' => now(),
            'reopened_by' => null,
            'reopened_at' => null,
            'reopen_reason' => null,
        ];
    }

    /**
     * The month that contains the given business date (Y-m-d).
     */
    public function forMonth(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'month' => substr($date, 0, 7).'-01',
        ]);
    }

    /**
     * Indicate that the month was reopened.
     */
    public function reopened(): static
    {
        return $this->state(fn (array $attributes) => [
            'reopened_by' => User::factory(),
            'reopened_at' => now(),
            'reopen_reason' => fake()->sentence(),
        ]);
    }
}
