<?php

namespace Database\Factories;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'guard_name' => config('auth.defaults.guard'),
            'is_system' => false,
        ];
    }

    /**
     * Give the role exactly these permissions. They must already be seeded.
     */
    public function withPermissions(Permission ...$permissions): static
    {
        return $this->afterCreating(function (Role $role) use ($permissions): void {
            $role->syncPermissions(array_map(fn (Permission $permission) => $permission->value, $permissions));
        });
    }
}
