<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MealTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of meal served in a day, such as Breakfast or Lunch. Never deleted:
 * switch it off to stop recording it.
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'sort_order', 'is_active'])]
class MealType extends Model
{
    /** @use HasFactory<MealTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * @param  Builder<MealType>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<MealType>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<Meal, $this>
     */
    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return ['name' => $this->name, 'sort_order' => $this->sort_order, 'is_active' => $this->is_active];
    }
}
