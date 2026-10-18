<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberStay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberStay>
 */
class MemberStayFactory extends Factory
{
    /**
     * Define the model's default state: an open stay.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory()->active(withStay: false),
            'joined_on' => fake()->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d'),
            'left_on' => null,
        ];
    }

    /**
     * A stay between the two business dates (Y-m-d); open when `$to` is null.
     */
    public function between(string $from, ?string $to = null): static
    {
        return $this->state(fn (array $attributes) => [
            'joined_on' => $from,
            'left_on' => $to,
        ]);
    }
}
