<?php

namespace App\Models;

use App\Concerns\BusinessCalendar;
use App\Enums\MemberStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A person living in the mess, linked to exactly one user account. The NID
 * number is encrypted at rest; `nid_number_hash` carries the unique index.
 * NID data and file paths are hidden from serialization: controllers send
 * them explicitly, and only with `member.view_nid`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string $phone
 * @property string $nid_number
 * @property string $nid_number_hash
 * @property string $father_name
 * @property string $mother_name
 * @property string $permanent_address
 * @property string $nid_file_path
 * @property string|null $photo_path
 * @property string|null $banner_path
 * @property MemberStatus $status
 * @property string|null $rejection_reason
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $full_name
 */
#[Fillable([
    'user_id',
    'first_name',
    'last_name',
    'phone',
    'nid_number',
    'father_name',
    'mother_name',
    'permanent_address',
    'nid_file_path',
    'photo_path',
    'banner_path',
])]
#[Hidden(['nid_number', 'nid_number_hash', 'nid_file_path', 'photo_path', 'banner_path'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Member $member): void {
            if ($member->isDirty('nid_number')) {
                $member->nid_number_hash = self::nidHash($member->nid_number);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nid_number' => 'encrypted',
            'status' => MemberStatus::class,
            'reviewed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * The keyed hash that makes an NID number unique without storing it in
     * the clear.
     */
    public static function nidHash(string $nidNumber): string
    {
        return hash_hmac('sha256', $nidNumber, (string) config('app.key'));
    }

    /**
     * Members with a stay overlapping any day of the month, evaluated on
     * Asia/Dhaka business dates. A partial month counts as a full one.
     *
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function activeInMonth(Builder $query, int $year, int $month): void
    {
        [$first, $last] = BusinessCalendar::monthDates($year, $month);

        $query->whereHas('stays', fn (Builder $stays) => $stays->overlapping($first, $last));
    }

    /**
     * Whether any stay overlaps the month of the given business date (Y-m-d).
     */
    public function isActiveInMonthOf(string $date): bool
    {
        return static::query()
            ->whereKey($this->id)
            ->activeInMonth((int) substr($date, 0, 4), (int) substr($date, 5, 2))
            ->exists();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Meal, $this>
     */
    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    /**
     * @return HasMany<MemberStay, $this>
     */
    public function stays(): HasMany
    {
        return $this->hasMany(MemberStay::class);
    }

    /**
     * @return HasOne<MemberStay, $this>
     */
    public function currentStay(): HasOne
    {
        return $this->hasOne(MemberStay::class)->whereNull('left_on');
    }

    /**
     * @return HasOne<MemberStay, $this>
     */
    public function latestStay(): HasOne
    {
        return $this->hasOne(MemberStay::class)->latestOfMany('joined_on');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * "First Last", which `users.name` is kept in sync with.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * @return HasMany<Deposit, $this>
     */
    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    /**
     * Purchases the member made as the buyer, voided ones included.
     *
     * @return HasMany<GroceryPurchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(GroceryPurchase::class, 'buyer_member_id');
    }

    /**
     * @return HasMany<BuyerPayable, $this>
     */
    public function payables(): HasMany
    {
        return $this->hasMany(BuyerPayable::class);
    }

    /**
     * Whether the member has purchases, deposits or buyer payables. Any
     * of them counts, whatever its status (a voided purchase too); the
     * RESTRICT foreign keys on those tables back it up.
     */
    public function hasFinancialRecords(): bool
    {
        return $this->deposits()->exists()
            || $this->purchases()->exists()
            || GroceryPurchase::query()->where('paid_by_member_id', $this->id)->exists()
            || $this->payables()->exists();
    }

    /**
     * The fields recorded as before/after values in the audit log. The NID
     * number itself is never copied into the log.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->user->email,
            'phone' => $this->phone,
            'father_name' => $this->father_name,
            'mother_name' => $this->mother_name,
            'permanent_address' => $this->permanent_address,
            'nid_number' => '[hidden]',
            'nid_file' => $this->nid_file_path !== '' ? 'on file' : null,
            'photo' => $this->photo_path !== null ? 'on file' : null,
            'banner' => $this->banner_path !== null ? 'on file' : null,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'stays' => $this->stays()->orderBy('joined_on')->get()->map(fn (MemberStay $stay) => [
                'joined_on' => $stay->joined_on->toDateString(),
                'left_on' => $stay->left_on?->toDateString(),
            ])->all(),
        ];
    }
}
