<?php

namespace Tests\Concerns;

use App\Actions\Ledger\PostAdjustment;
use App\Concerns\BusinessCalendar;
use App\Enums\LedgerDirection;
use App\Enums\Permission;
use App\Models\GroceryPurchase;
use App\Models\Member;
use App\Models\User;

/**
 * Shared setup for grocery tests: a fixed "now" (18 Oct 2026, noon in
 * Dhaka), a way to put money in the fund, and valid form input.
 */
trait WorksWithPurchases
{
    use AssertsLedgerBalance, SeedsAccessControl;

    protected function setUpWorksWithPurchases(): void
    {
        $this->travelTo(BusinessCalendar::today()->setDate(2026, 10, 18)->setTime(12, 0));
    }

    protected function activeMember(): Member
    {
        return Member::factory()->active()->create();
    }

    protected function staff(Permission ...$permissions): User
    {
        return User::factory()->withPermissions(Permission::GroceryView, ...$permissions)->create();
    }

    /**
     * Put money in the fund through the ledger.
     */
    protected function fund(string $amount, string $date = '2026-10-01'): void
    {
        app(PostAdjustment::class)->handle(User::factory()->create(), [
            'direction' => LedgerDirection::Credit,
            'amount' => $amount,
            'entry_date' => $date,
            'reason' => 'Test funding',
        ]);
    }

    /**
     * Two items: 2.5 kg × 80.00 = 200.00 and 12 pcs × 12.50 = 150.00.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function purchaseForm(array $overrides = []): array
    {
        return [
            'purchase_date' => '2026-10-17',
            'items' => [
                ['item_name' => 'Rice', 'quantity' => '2.5', 'unit' => 'kg', 'unit_price' => '80.00'],
                ['item_name' => 'Eggs', 'quantity' => '12', 'unit' => 'pcs', 'unit_price' => '12.50'],
            ],
            ...$overrides,
        ];
    }

    /**
     * Record a purchase through the app as the member, returning it.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function recordPurchase(Member $member, array $overrides = []): GroceryPurchase
    {
        $this->actingAs($member->user)
            ->post(route('grocery.store'), $this->purchaseForm($overrides))
            ->assertSessionHasNoErrors();

        return GroceryPurchase::query()->latest('id')->firstOrFail();
    }
}
