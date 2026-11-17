<?php

namespace Tests\Feature\Grocery;

use App\Enums\GroceryPurchaseStatus;
use App\Enums\LedgerEntryType;
use App\Enums\Permission;
use App\Exceptions\ImmutableRecord;
use App\Models\Activity;
use App\Models\GroceryPurchase;
use App\Models\LedgerEntry;
use App\Models\MonthClosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WorksWithPurchases;
use Tests\TestCase;

class PurchaseVoidTest extends TestCase
{
    use RefreshDatabase, WorksWithPurchases;

    public function test_the_owner_voids_within_the_window_without_a_reason()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $this->actingAs($member->user)
            ->post(route('grocery.void', $purchase))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('grocery.index'));

        $purchase->refresh();
        $this->assertSame(GroceryPurchaseStatus::Voided, $purchase->status);
        $this->assertSame('Deleted by owner', $purchase->void_reason);
        $this->assertSame($member->user_id, $purchase->voided_by);
        $this->assertNotNull($purchase->voided_at);

        // Nothing is deleted: the row, its items and its entries remain.
        $this->assertSame(2, $purchase->items()->count());
        $reversal = LedgerEntry::query()->where('type', LedgerEntryType::Reversal)->sole();
        $this->assertSame('350.00', $reversal->amount);
        $this->assertSame('2026-10-17', $reversal->entry_date->toDateString());
        $this->assertNull($purchase->expenseEntry);
        $this->assertFundMatchesLedger('1000.00');
    }

    public function test_after_the_window_the_owner_cannot_void()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $this->travel(31)->minutes();

        $this->actingAs($member->user)->post(route('grocery.void', $purchase))->assertForbidden();

        $this->assertSame(GroceryPurchaseStatus::Active, $purchase->fresh()->status);
        $this->assertFundMatchesLedger('650.00');
    }

    public function test_staff_must_give_a_reason()
    {
        $this->fund('1000.00');
        $purchase = $this->recordPurchase($this->activeMember());
        $manager = $this->staff(Permission::GroceryDeleteAny);

        $this->actingAs($manager)
            ->post(route('grocery.void', $purchase), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(GroceryPurchaseStatus::Active, $purchase->fresh()->status);

        $this->actingAs($manager)
            ->post(route('grocery.void', $purchase), ['reason' => 'Entered twice.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Entered twice.', $purchase->fresh()->void_reason);
        $this->assertFundMatchesLedger('1000.00');
    }

    public function test_voiding_needs_a_delete_permission()
    {
        $this->fund('1000.00');
        $purchase = $this->recordPurchase($this->activeMember());

        $this->actingAs($this->activeMember()->user)->post(route('grocery.void', $purchase))->assertForbidden();
        $this->actingAs($this->staff(Permission::GroceryUpdateAny))->post(route('grocery.void', $purchase), ['reason' => 'x'])->assertForbidden();
    }

    public function test_a_purchase_is_voided_only_once()
    {
        $this->fund('1000.00');
        $purchase = $this->recordPurchase($this->activeMember());
        $manager = $this->staff(Permission::GroceryDeleteAny);

        $this->actingAs($manager)->post(route('grocery.void', $purchase), ['reason' => 'Mistake']);
        $this->actingAs($manager)->post(route('grocery.void', $purchase), ['reason' => 'Again'])->assertForbidden();

        $this->assertSame(1, LedgerEntry::query()->where('type', LedgerEntryType::Reversal)->count());
        $this->assertFundMatchesLedger('1000.00');
    }

    public function test_a_purchase_in_a_closed_month_cannot_be_voided()
    {
        $this->fund('1000.00', '2026-09-01');
        $purchase = $this->recordPurchase($this->activeMember(), ['purchase_date' => '2026-09-28']);
        MonthClosure::factory()->forMonth('2026-09-01')->create();

        $this->actingAs($this->staff(Permission::GroceryDeleteAny))
            ->post(route('grocery.void', $purchase), ['reason' => 'Mistake'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(GroceryPurchaseStatus::Active, $purchase->fresh()->status);
        $this->assertFundMatchesLedger('650.00');
    }

    public function test_voided_purchases_are_hidden_from_the_list_but_keep_their_page()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $kept = $this->recordPurchase($member);
        $voided = $this->recordPurchase($member);

        $this->actingAs($member->user)->post(route('grocery.void', $voided));

        $this->actingAs($member->user)
            ->get(route('grocery.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('grocery/index')
                ->has('purchases.data', 1)
                ->where('purchases.data.0.id', $kept->id)
                ->where('total', '350.00'));

        $this->actingAs($member->user)
            ->get(route('grocery.show', $voided))
            ->assertInertia(fn (Assert $page) => $page
                ->where('purchase.status', 'voided')
                ->where('purchase.void_reason', 'Deleted by owner')
                ->where('purchase.can.update', false));
    }

    public function test_a_void_is_audit_logged_with_the_full_record()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $this->actingAs($member->user)->post(route('grocery.void', $purchase));

        $changes = Activity::query()->where('description', 'purchase_voided')->sole()->attribute_changes;
        $this->assertSame('active', $changes['old']['status']);
        $this->assertSame('voided', $changes['attributes']['status']);
        $this->assertSame('350.00', $changes['old']['fund_paid']);
        $this->assertSame('0.00', $changes['attributes']['fund_paid']);
        $this->assertCount(2, $changes['old']['items']);
        $this->assertSame('650.00', $changes['old']['balance']);
        $this->assertSame('1000.00', $changes['attributes']['balance']);
    }

    public function test_purchases_cannot_be_hard_deleted()
    {
        $purchase = GroceryPurchase::factory()->create();

        $this->expectException(ImmutableRecord::class);

        $purchase->delete();
    }
}
