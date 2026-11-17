<?php

namespace Tests\Feature\Grocery;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\GroceryPurchase;
use App\Models\LedgerEntry;
use App\Models\MessSetting;
use App\Models\MonthClosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WorksWithPurchases;
use Tests\TestCase;

class PurchaseEditTest extends TestCase
{
    use RefreshDatabase, WorksWithPurchases;

    /**
     * One item: 3 kg × 100.00 = 300.00.
     *
     * @return array<string, mixed>
     */
    private function editedForm(array $overrides = []): array
    {
        return $this->purchaseForm([
            'items' => [['item_name' => 'Lentils', 'quantity' => '3', 'unit' => 'kg', 'unit_price' => '100.00']],
            ...$overrides,
        ]);
    }

    public function test_the_owner_edits_within_the_window_and_the_ledger_gets_a_reversal_and_a_new_entry()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);
        $original = $purchase->expenseEntry;

        $this->travel(29)->minutes();

        $this->actingAs($member->user)
            ->put(route('grocery.update', $purchase), $this->editedForm())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('grocery.show', $purchase));

        $purchase->refresh();
        $this->assertSame('300.00', $purchase->grand_total);
        $this->assertSame(['Lentils'], $purchase->items->pluck('item_name')->all());
        $this->assertSame($member->user_id, $purchase->updated_by);

        $reversal = LedgerEntry::query()->where('type', LedgerEntryType::Reversal)->sole();
        $this->assertSame($original->id, $reversal->reverses_entry_id);
        $this->assertSame(LedgerDirection::Credit, $reversal->direction);
        $this->assertSame('350.00', $reversal->amount);

        $this->assertSame('300.00', $purchase->expenseEntry->amount);
        $this->assertNotSame($original->id, $purchase->expenseEntry->id);
        $this->assertSame('350.00', $original->fresh()->amount);
        $this->assertFundMatchesLedger('700.00');
    }

    public function test_after_the_window_the_owner_gets_a_403_and_the_purchase_shows_as_locked()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $this->actingAs($member->user)
            ->get(route('grocery.show', $purchase))
            ->assertInertia(fn (Assert $page) => $page
                ->where('purchase.locked', false)
                ->where('purchase.can.update', true)
                ->where('purchase.can.delete', true)
                ->where('purchase.window_closes_at', $purchase->created_at->addMinutes(30)->toIso8601String()));

        $this->travel(30)->minutes();

        $this->actingAs($member->user)->get(route('grocery.edit', $purchase))->assertForbidden();
        $this->actingAs($member->user)->put(route('grocery.update', $purchase), $this->editedForm())->assertForbidden();

        $this->actingAs($member->user)
            ->get(route('grocery.show', $purchase))
            ->assertInertia(fn (Assert $page) => $page
                ->where('purchase.locked', true)
                ->where('purchase.can.update', false)
                ->where('purchase.can.delete', false)
                ->where('purchase.window_closes_at', null));

        $this->assertSame('350.00', $purchase->fresh()->grand_total);
        $this->assertFundMatchesLedger('650.00');
    }

    public function test_the_window_length_comes_from_the_settings()
    {
        $this->fund('1000.00');
        MessSetting::current()->update(['grocery_edit_window_minutes' => 5]);
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $this->travel(6)->minutes();

        $this->actingAs($member->user)->put(route('grocery.update', $purchase), $this->editedForm())->assertForbidden();
    }

    public function test_a_member_cannot_edit_someone_elses_purchase()
    {
        $this->fund('1000.00');
        $purchase = $this->recordPurchase($this->activeMember());

        $this->actingAs($this->activeMember()->user)
            ->put(route('grocery.update', $purchase), $this->editedForm())
            ->assertForbidden();
    }

    public function test_update_any_bypasses_the_window()
    {
        $this->fund('1000.00');
        $purchase = $this->recordPurchase($this->activeMember());
        $manager = $this->staff(Permission::GroceryUpdateAny);

        $this->travel(3)->days();

        $this->actingAs($manager)
            ->put(route('grocery.update', $purchase), $this->editedForm())
            ->assertSessionHasNoErrors();

        $this->assertSame('300.00', $purchase->fresh()->grand_total);
        $this->assertSame($manager->id, $purchase->fresh()->updated_by);
        $this->assertFundMatchesLedger('700.00');
    }

    public function test_an_edit_that_changes_no_money_posts_nothing_to_the_ledger()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $form = $this->purchaseForm(['notes' => 'From Karwan Bazar']);
        $form['items'][0]['item_name'] = 'Miniket rice';

        $this->actingAs($member->user)->put(route('grocery.update', $purchase), $form)->assertSessionHasNoErrors();

        $this->assertSame('Miniket rice', $purchase->fresh()->items->first()->item_name);
        $this->assertSame(0, LedgerEntry::query()->where('type', LedgerEntryType::Reversal)->count());
        $this->assertFundMatchesLedger('650.00');
    }

    public function test_changing_the_date_moves_the_expense_to_the_new_date()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $this->actingAs($member->user)
            ->put(route('grocery.update', $purchase), $this->purchaseForm(['purchase_date' => '2026-10-12']))
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-10-17', LedgerEntry::query()->where('type', LedgerEntryType::Reversal)->sole()->entry_date->toDateString());
        $this->assertSame('2026-10-12', $purchase->fresh()->expenseEntry->entry_date->toDateString());
        $this->assertFundMatchesLedger('650.00');
    }

    public function test_a_purchase_in_a_closed_month_cannot_be_edited_or_moved_into_one()
    {
        $this->fund('1000.00', '2026-09-01');
        $manager = $this->staff(Permission::GroceryUpdateAny);
        $september = $this->recordPurchase($this->activeMember(), ['purchase_date' => '2026-09-28']);
        $october = $this->recordPurchase($this->activeMember());
        MonthClosure::factory()->forMonth('2026-09-01')->create();

        $this->actingAs($manager)
            ->put(route('grocery.update', $september), $this->editedForm(['purchase_date' => '2026-10-02']))
            ->assertSessionHasErrors('purchase_date');

        $this->actingAs($manager)
            ->put(route('grocery.update', $october), $this->editedForm(['purchase_date' => '2026-09-29']))
            ->assertSessionHasErrors('purchase_date');

        $this->assertSame('350.00', $september->fresh()->grand_total);
        $this->assertSame('350.00', $october->fresh()->grand_total);
        $this->assertFundMatchesLedger('300.00');
    }

    public function test_only_staff_who_record_for_others_can_change_the_buyer()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $other = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $this->actingAs($member->user)
            ->put(route('grocery.update', $purchase), $this->purchaseForm(['buyer_member_id' => $other->id]))
            ->assertForbidden();

        $this->actingAs($this->staff(Permission::GroceryUpdateAny, Permission::GroceryCreateForOthers))
            ->put(route('grocery.update', $purchase), $this->purchaseForm(['buyer_member_id' => $other->id]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($purchase->fresh()->buyer->is($other));
    }

    public function test_an_edit_is_audit_logged_with_the_old_and_new_item_lists()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $this->actingAs($member->user)->put(route('grocery.update', $purchase), $this->editedForm());

        $changes = Activity::query()->where('description', 'purchase_updated')->sole()->attribute_changes;
        $this->assertSame(['Rice', 'Eggs'], array_column($changes['old']['items'], 'item_name'));
        $this->assertSame(['Lentils'], array_column($changes['attributes']['items'], 'item_name'));
        $this->assertSame('350.00', $changes['old']['grand_total']);
        $this->assertSame('300.00', $changes['attributes']['grand_total']);
        $this->assertSame('650.00', $changes['old']['balance']);
        $this->assertSame('700.00', $changes['attributes']['balance']);
    }

    public function test_a_voided_purchase_cannot_be_edited()
    {
        $purchase = GroceryPurchase::factory()->voided()->create();

        $this->actingAs($this->staff(Permission::GroceryUpdateAny))
            ->put(route('grocery.update', $purchase), $this->editedForm())
            ->assertForbidden();
    }
}
