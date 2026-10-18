<?php

namespace App\Models;

use App\Enums\SystemRole;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property bool $is_system
 */
class Role extends SpatieRole
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * The seeded Admin role.
     */
    public static function admin(): self
    {
        return static::query()
            ->where('is_system', true)
            ->where('name', SystemRole::Admin->value)
            ->firstOrFail();
    }

    /**
     * The seeded Member role, given to every approved member.
     */
    public static function member(): self
    {
        return static::query()
            ->where('is_system', true)
            ->where('name', SystemRole::Member->value)
            ->firstOrFail();
    }

    public function isAdmin(): bool
    {
        return $this->is_system && $this->name === SystemRole::Admin->value;
    }

    /**
     * The fields recorded as before/after values in the audit log.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'name' => $this->name,
            'permissions' => $this->permissions()->pluck('name')->sort()->values()->all(),
        ];
    }
}
