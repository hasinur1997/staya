<?php

namespace App\Models;

use App\Concerns\Money;
use App\Enums\LedgerEntryType;
use App\Exceptions\ImmutableRecord;
use Carbon\CarbonImmutable;
use Database\Factories\BuyerPayableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * What the fund owes a buyer because it could not cover their purchase.
 * One row per purchase. `amount_reimbursed` always equals the payable's
 * reimbursement ledger entries that are still in force; the payable is
 * open while it is less than `amount`.
 *
 * @property int $id
 * @property int $grocery_purchase_id
 * @property int $member_id
 * @property string $amount
 * @property string $amount_reimbursed
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read GroceryPurchase $purchase
 * @property-read Member $member
 */
#[Fillable(['grocery_purchase_id', 'member_id', 'amount', 'amount_reimbursed'])]
class BuyerPayable extends Model
{
    /** @use HasFactory<BuyerPayableFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'amount_reimbursed' => '0.00',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (BuyerPayable $payable) => throw ImmutableRecord::deletion($payable->getTable()));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_reimbursed' => 'decimal:2',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Payables with money still owed.
     *
     * @param  Builder<BuyerPayable>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereColumn('amount_reimbursed', '<', 'amount');
    }

    /**
     * What is still owed to the buyer.
     *
     * @return numeric-string
     */
    public function outstanding(): string
    {
        return bcsub(Money::numeric($this->amount), Money::numeric($this->amount_reimbursed), Money::SCALE);
    }

    public function isOpen(): bool
    {
        return bccomp($this->outstanding(), '0', Money::SCALE) > 0;
    }

    /**
     * @return BelongsTo<GroceryPurchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(GroceryPurchase::class, 'grocery_purchase_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Every ledger entry this payable caused: reimbursements and reversals.
     *
     * @return MorphMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }

    /**
     * Reimbursements in force: the ones not reversed.
     *
     * @return MorphMany<LedgerEntry, $this>
     */
    public function reimbursementEntries(): MorphMany
    {
        return $this->ledgerEntries()
            ->where('type', LedgerEntryType::Reimbursement)
            ->whereDoesntHave('reversedBy');
    }

    /**
     * The fields recorded as before/after values in the audit log.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'grocery_purchase_id' => $this->grocery_purchase_id,
            'member_id' => $this->member_id,
            'amount' => $this->amount,
            'amount_reimbursed' => $this->amount_reimbursed,
        ];
    }
}
