<?php

namespace App\Models;

use App\Casts\BusinessDate;
use App\Enums\GroceryPaidBy;
use App\Enums\GroceryPurchaseStatus;
use App\Enums\LedgerEntryType;
use App\Exceptions\ImmutableRecord;
use Carbon\CarbonImmutable;
use Database\Factories\GroceryPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A grocery purchase paid from the shared fund. Its grand total is the sum
 * of its line totals, computed on the server. The fund pays what it holds
 * (the live `grocery_expense` ledger entry); any shortfall is a payable
 * owed to the buyer. A purchase a member paid for from their own pocket
 * leaves the fund alone: the whole total is a payable owed to that member.
 * Purchases are voided, never deleted.
 *
 * @property int $id
 * @property CarbonImmutable $purchase_date
 * @property int $buyer_member_id
 * @property string $grand_total
 * @property GroceryPaidBy $paid_by
 * @property int|null $paid_by_member_id
 * @property string|null $notes
 * @property string|null $receipt_path
 * @property GroceryPurchaseStatus $status
 * @property string|null $void_reason
 * @property int|null $voided_by
 * @property CarbonImmutable|null $voided_at
 * @property int $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Member $buyer
 * @property-read Member|null $payer
 * @property-read Collection<int, GroceryPurchaseItem> $items
 * @property-read BuyerPayable|null $payable
 */
#[Fillable(['purchase_date', 'buyer_member_id', 'grand_total', 'paid_by', 'paid_by_member_id', 'notes', 'receipt_path', 'created_by', 'updated_by'])]
#[Hidden(['receipt_path'])]
class GroceryPurchase extends Model
{
    /** @use HasFactory<GroceryPurchaseFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'paid_by' => 'fund',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (GroceryPurchase $purchase) => throw ImmutableRecord::deletion($purchase->getTable()));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_date' => BusinessDate::class,
            'grand_total' => 'decimal:2',
            'paid_by' => GroceryPaidBy::class,
            'status' => GroceryPurchaseStatus::class,
            'voided_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Purchases that count: voided ones are hidden from lists and totals.
     *
     * @param  Builder<GroceryPurchase>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', GroceryPurchaseStatus::Active);
    }

    public function isVoided(): bool
    {
        return $this->status === GroceryPurchaseStatus::Voided;
    }

    /**
     * Whether the user recorded the purchase. The creator, not the buyer,
     * owns it for the edit window.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->created_by === $user->id;
    }

    /**
     * When the owner's edit window closes, from the server's `created_at`.
     */
    public function editWindowClosesAt(): CarbonImmutable
    {
        return MessSetting::current()->editWindowClosesAt($this->created_at);
    }

    public function isWithinEditWindow(): bool
    {
        return now()->lt($this->editWindowClosesAt());
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'buyer_member_id')->withTrashed();
    }

    /**
     * The member who paid from their own pocket; null when the fund paid.
     *
     * @return BelongsTo<Member, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'paid_by_member_id')->withTrashed();
    }

    public function isPaidByMember(): bool
    {
        return $this->paid_by === GroceryPaidBy::Member;
    }

    /**
     * The member a payable for this purchase is owed to: whoever paid from
     * their own pocket, otherwise the buyer (for a fund shortfall).
     */
    public function payeeMemberId(): int
    {
        return $this->isPaidByMember() && $this->paid_by_member_id !== null
            ? $this->paid_by_member_id
            : $this->buyer_member_id;
    }

    /**
     * @return HasMany<GroceryPurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GroceryPurchaseItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasOne<BuyerPayable, $this>
     */
    public function payable(): HasOne
    {
        return $this->hasOne(BuyerPayable::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Every ledger entry this purchase caused: expenses and their reversals.
     *
     * @return MorphMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }

    /**
     * The expense entry in force: the one not reversed yet. There is none
     * when the fund was empty or the purchase is voided.
     *
     * @return MorphOne<LedgerEntry, $this>
     */
    public function expenseEntry(): MorphOne
    {
        return $this->morphOne(LedgerEntry::class, 'source')
            ->where('type', LedgerEntryType::GroceryExpense)
            ->whereDoesntHave('reversedBy');
    }

    /**
     * The fields recorded as before/after values in the audit log, with the
     * full item list and the payable.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        $payable = $this->payable()->first();

        return [
            'purchase_date' => $this->purchase_date->toDateString(),
            'buyer_member_id' => $this->buyer_member_id,
            'grand_total' => $this->grand_total,
            'paid_by' => $this->paid_by->value,
            'paid_by_member_id' => $this->paid_by_member_id,
            'notes' => $this->notes,
            'receipt' => $this->receipt_path !== null ? 'on file' : null,
            'status' => $this->status->value,
            'void_reason' => $this->void_reason,
            'items' => $this->items()->get()->map(fn (GroceryPurchaseItem $item) => [
                'item_name' => $item->item_name,
                'quantity' => $item->quantity,
                'unit' => $item->unit->value,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->all(),
            'fund_paid' => $this->expenseEntry()->first()->amount ?? '0.00',
            'payable' => $payable === null ? null : [
                'member_id' => $payable->member_id,
                'amount' => $payable->amount,
                'amount_reimbursed' => $payable->amount_reimbursed,
            ],
        ];
    }
}
