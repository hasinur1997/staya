<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Permissions are normally seeded from the catalog; this factory is for
 * tests that need a permission outside it.
 *
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $group = fake()->unique()->word();

        return [
            'name' => $group.'.'.fake()->word(),
            'guard_name' => config('auth.defaults.guard'),
            'group' => $group,
            'description' => fake()->sentence(),
        ];
    }
}
