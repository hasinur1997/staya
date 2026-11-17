<?php

namespace App\Models;

use App\Casts\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\MealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Meals of one type a member had on one business date (1 to 10).
 *
 * @property int $id
 * @property int $member_id
 * @property int $meal_type_id
 * @property CarbonImmutable $meal_date
 * @property int $count
 * @property int $recorded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['member_id', 'meal_type_id', 'meal_date', 'count', 'recorded_by'])]
class Meal extends Model
{
    /** @use HasFactory<MealFactory> */
    use HasFactory;

    public const int MAX_PER_TYPE = 10;

    protected function casts(): array
    {
        return ['meal_date' => BusinessDate::class, 'count' => 'integer'];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<MealType, $this>
     */
    public function mealType(): BelongsTo
    {
        return $this->belongsTo(MealType::class);
    }
}
