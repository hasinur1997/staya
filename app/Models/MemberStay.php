<?php

namespace App\Models;

use App\Casts\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\MemberStayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One period a member lived in the mess. `left_on` is null while the stay
 * is open. Past stays are never rewritten: deactivating closes the open
 * stay and reactivating opens a new one.
 *
 * @property int $id
 * @property int $member_id
 * @property CarbonImmutable $joined_on
 * @property CarbonImmutable|null $left_on
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['member_id', 'joined_on', 'left_on'])]
class MemberStay extends Model
{
    /** @use HasFactory<MemberStayFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_on' => BusinessDate::class,
            'left_on' => BusinessDate::class,
        ];
    }

    /**
     * Stays that include at least one day between the two business dates
     * (Y-m-d, inclusive).
     *
     * @param  Builder<MemberStay>  $query
     */
    #[Scope]
    protected function overlapping(Builder $query, string $from, string $to): void
    {
        $query->where('joined_on', '<=', $to)
            ->where(fn (Builder $open) => $open->whereNull('left_on')->orWhere('left_on', '>=', $from));
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
