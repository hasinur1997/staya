<?php

namespace Database\Factories;

use App\Models\MessSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * There is only ever one settings row; prefer `MessSetting::current()`.
 *
 * @extends Factory<MessSetting>
 */
class MessSettingFactory extends Factory
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
            'mess_name' => fake()->company(),
            'mess_address' => fake()->address(),
            'opening_balance' => '0.00',
            'fund_start_date' => null,
            'grocery_edit_window_minutes' => 30,
            'members_can_view_balance' => true,
            'max_upload_kb' => 5120,
        ];
    }
}
