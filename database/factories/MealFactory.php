<?php

namespace Database\Factories;

use App\Models\Meal;
use App\Models\MealType;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meal>
 */
class MealFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory()->active(),
            'meal_type_id' => MealType::factory(),
            'meal_date' => '2026-10-10',
            'count' => 1,
            'recorded_by' => User::factory(),
        ];
    }
}
