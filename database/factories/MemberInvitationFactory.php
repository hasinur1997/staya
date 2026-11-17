<?php

namespace Database\Factories;

use App\Models\MemberInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberInvitation>
 */
class MemberInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => MemberInvitation::hashToken(fake()->unique()->sha256()),
            'invited_by' => User::factory(),
            'expires_at' => now()->addDays(7),
        ];
    }

    /**
     * An invitation with a known plain token, for opening its link.
     */
    public function withToken(string $token): static
    {
        return $this->state(['token_hash' => MemberInvitation::hashToken($token)]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subMinute()]);
    }

    public function used(): static
    {
        return $this->state(['used_at' => now()]);
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()]);
    }
}
