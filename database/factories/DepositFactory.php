<?php

namespace Database\Factories;

use App\Actions\Ledger\PostLedgerEntries;
use App\Concerns\BusinessCalendar;
use App\Enums\DepositMethod;
use App\Enums\DepositStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deposit>
 */
class DepositFactory extends Factory
{
    /**
     * Define the model's default state: a pending cash deposit dated today.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory()->active(),
            'amount' => fake()->numberBetween(500, 5000).'.00',
            'deposit_date' => BusinessCalendar::today()->toDateString(),
            'method' => DepositMethod::Cash,
            'status' => DepositStatus::Pending,
            'submitted_by' => fn (array $attributes) => Member::query()->whereKey($attributes['member_id'])->value('user_id'),
        ];
    }

    /**
     * An approved deposit with its credit posted through the ledger, so the
     * fund balance matches.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DepositStatus::Approved,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ])->afterCreating(function (Deposit $deposit): void {
            app(PostLedgerEntries::class)->handle($deposit->reviewer()->firstOrFail(), [[
                'type' => LedgerEntryType::Deposit,
                'direction' => LedgerDirection::Credit,
                'amount' => $deposit->amount,
                'entry_date' => $deposit->deposit_date->toDateString(),
                'source' => $deposit,
            ]]);
        });
    }

    public function rejected(string $reason = 'No cash was received.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DepositStatus::Rejected,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DepositStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
