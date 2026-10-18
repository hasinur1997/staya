<?php

namespace Database\Factories;

use App\Enums\AuditLog;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'log_name' => AuditLog::Access->value,
            'description' => fake()->sentence(),
            'event' => 'updated',
            'causer_type' => 'user',
            'causer_id' => User::factory(),
            'attribute_changes' => null,
            'properties' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }

    /**
     * Record before/after values for the given attributes.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function withChanges(array $old, array $new): static
    {
        return $this->state(fn (): array => [
            'attribute_changes' => ['old' => $old, 'attributes' => $new],
        ]);
    }
}
