<?php

namespace Database\Factories;

use App\Actions\Grocery\SettlePurchase;
use App\Concerns\BusinessCalendar;
use App\Enums\GroceryPurchaseStatus;
use App\Models\GroceryPurchase;
use App\Models\GroceryPurchaseItem;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<GroceryPurchase>
 */
class GroceryPurchaseFactory extends Factory
{
    /**
     * Define the model's default state: an active purchase dated today,
     * recorded by its buyer. It gets one item matching the grand total
     * unless items are created for it. Nothing is posted to the ledger;
     * use `settled()` for that.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_date' => BusinessCalendar::today()->toDateString(),
            'buyer_member_id' => Member::factory()->active(),
            'grand_total' => fake()->numberBetween(100, 2000).'.00',
            'notes' => null,
            'receipt_path' => null,
            'status' => GroceryPurchaseStatus::Active,
            'created_by' => fn (array $attributes) => Member::query()->whereKey($attributes['buyer_member_id'])->value('user_id'),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (GroceryPurchase $purchase): void {
            if (! $purchase->items()->exists()) {
                GroceryPurchaseItem::factory()->for($purchase, 'purchase')->create([
                    'quantity' => '1.000',
                    'unit_price' => $purchase->grand_total,
                    'line_total' => $purchase->grand_total,
                ]);
            }
        });
    }

    /**
     * Pay for the purchase the way the app does: an expense for what the
     * fund holds and a buyer payable for the rest.
     */
    public function settled(): static
    {
        return $this->afterCreating(function (GroceryPurchase $purchase): void {
            DB::transaction(fn () => app(SettlePurchase::class)->handle(
                $purchase->creator()->firstOrFail(),
                $purchase,
                $purchase->grand_total,
                $purchase->purchase_date->toDateString(),
                'Purchase recorded',
            ));
        });
    }

    /**
     * A voided purchase that never touched the ledger.
     */
    public function voided(string $reason = 'Entered twice.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GroceryPurchaseStatus::Voided,
            'void_reason' => $reason,
            'voided_by' => User::factory(),
            'voided_at' => now(),
        ]);
    }
}
