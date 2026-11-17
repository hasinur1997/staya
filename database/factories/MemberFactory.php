<?php

namespace Database\Factories;

use App\Concerns\BusinessCalendar;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberStay;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state: a pending applicant.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'name_bn' => 'রহিম উদ্দিন',
            'phone' => '+8801'.fake()->numberBetween(3, 9).fake()->numerify('########'),
            'nid_number' => fake()->unique()->numerify('##########'),
            'father_name' => fake()->name('male'),
            'father_name_bn' => 'করিম উদ্দিন',
            'mother_name' => fake()->name('female'),
            'mother_name_bn' => 'আমিনা বেগম',
            'permanent_address' => fake()->address(),
            'nid_file_path' => 'members/nid/'.fake()->uuid().'.pdf',
            'photo_path' => null,
            'status' => MemberStatus::Pending,
        ];
    }

    /**
     * Indicate that the registration was rejected.
     */
    public function rejected(string $reason = 'The NID copy is unreadable.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MemberStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * An approved member holding the Member role (run AccessControlSeeder
     * first), with an open stay that started a month ago unless told not to.
     */
    public function active(bool $withStay = true): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MemberStatus::Active,
            'reviewed_at' => now(),
        ])->afterCreating(function (Member $member) use ($withStay): void {
            $member->user->assignRole(Role::findByName('Member'));

            if ($withStay) {
                MemberStay::factory()->for($member)->between(
                    BusinessCalendar::today()->subMonth()->toDateString(),
                )->create();
            }
        });
    }

    /**
     * A member who left: Member role, one closed stay.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MemberStatus::Inactive,
            'reviewed_at' => now(),
        ])->afterCreating(function (Member $member): void {
            $member->user->assignRole(Role::findByName('Member'));

            $today = BusinessCalendar::today();

            MemberStay::factory()->for($member)->between(
                $today->subMonths(3)->toDateString(),
                $today->subMonth()->toDateString(),
            )->create();
        });
    }
}
