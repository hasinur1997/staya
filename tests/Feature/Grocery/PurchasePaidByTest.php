<?php

namespace Tests\Feature\Grocery;

use App\Enums\GroceryPaidBy;
use App\Enums\LedgerEntryType;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\BuyerPayable;
use App\Models\GroceryPurchase;
use App\Models\LedgerEntry;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WorksWithPurchases;
use Tests\TestCase;

class PurchasePaidByTest extends TestCase
{
    use RefreshDatabase, WorksWithPurchases;

    public function test_a_purchase_is_paid_by_the_fund_unless_told_otherwise()
    {
        $this->fund('1000.00');
        $purchase = $this->recordPurchase($this->activeMember());

        $this->assertSame(GroceryPaidBy::Fund, $purchase->paid_by);
        $this->assertNull($purchase->paid_by_member_id);
        $this->assertSame(0, BuyerPayable::query()->count());
        $this->assertFundMatchesLedger('650.00');
    }

    public function test_a_purchase_paid_by_a_member_leaves_the_fund_alone_and_owes_them_the_total()
    {
        $this->fund('1000.00');
        $buyer = $this->activeMember();
        $payer = $this->activeMember();

        $purchase = $this->recordPurchase($buyer, ['paid_by' => 'member', 'paid_by_member_id' => $payer->id]);

        $this->assertSame(GroceryPaidBy::Member, $purchase->paid_by);
        $this->assertTrue($purchase->payer->is($payer));
        $this->assertTrue($purchase->buyer->is($buyer));
        $this->assertSame(0, LedgerEntry::query()->where('type', LedgerEntryType::GroceryExpense)->count());

        $payable = BuyerPayable::query()->sole();
        $this->assertTrue($payable->member->is($payer));
        $this->assertSame('350.00', $payable->amount);
        $this->assertFundMatchesLedger('1000.00');

        $changes = Activity::query()->where('description', 'purchase_created')->sole()->attribute_changes;
        $this->assertSame('member', $changes['attributes']['paid_by']);
        $this->assertSame($payer->id, $changes['attributes']['paid_by_member_id']);
        $this->assertSame('0.00', $changes['attributes']['fund_paid']);
        $this->assertSame($payer->id, $changes['attributes']['payable']['member_id']);
    }

    public function test_paid_by_member_needs_an_active_member()
    {
        $member = $this->activeMember();
        $inactive = Member::factory()->inactive()->create();

        $manager = $this->staff(Permission::GroceryCreateForOthers);
        $form = ['buyer_member_id' => $member->id];

        $this->actingAs($manager)
            ->post(route('grocery.store'), $this->purchaseForm([...$form, 'paid_by' => 'member']))
            ->assertSessionHasErrors('paid_by_member_id');

        $this->actingAs($manager)
            ->post(route('grocery.store'), $this->purchaseForm([...$form, 'paid_by' => 'member', 'paid_by_member_id' => $inactive->id]))
            ->assertSessionHasErrors('paid_by_member_id');

        $this->actingAs($member->user)
            ->post(route('grocery.store'), $this->purchaseForm(['paid_by' => 'someone']))
            ->assertSessionHasErrors('paid_by');

        $this->assertSame(0, GroceryPurchase::query()->count());
    }

    public function test_an_ordinary_member_paying_means_themselves()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();

        $purchase = $this->recordPurchase($member, ['paid_by' => 'member']);

        $this->assertSame($member->id, $purchase->paid_by_member_id);
        $this->assertSame('350.00', $purchase->payable->amount);
        $this->assertFundMatchesLedger('1000.00');
    }

    public function test_an_ordinary_member_cannot_name_another_member_as_payer()
    {
        $member = $this->activeMember();
        $other = $this->activeMember();

        $this->actingAs($member->user)
            ->post(route('grocery.store'), $this->purchaseForm(['paid_by' => 'member', 'paid_by_member_id' => $other->id]))
            ->assertSessionHasErrors('paid_by_member_id');

        $this->assertSame(0, GroceryPurchase::query()->count());
    }

    public function test_only_staff_who_record_for_others_are_offered_the_payer_choice()
    {
        $member = $this->activeMember();
        $this->activeMember();

        $this->actingAs($member->user)->get(route('grocery.create'))
            ->assertInertia(fn (Assert $page) => $page->where('can.choosePayer', false)->has('payers', 0));

        $this->actingAs($this->staff(Permission::GroceryCreateForOthers))->get(route('grocery.create'))
            ->assertInertia(fn (Assert $page) => $page->where('can.choosePayer', true)->has('payers', 2));
    }

    public function test_a_member_given_with_paid_by_fund_is_ignored()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();

        $purchase = $this->recordPurchase($member, ['paid_by' => 'fund', 'paid_by_member_id' => $member->id]);

        $this->assertNull($purchase->paid_by_member_id);
        $this->assertFundMatchesLedger('650.00');
    }

    public function test_the_member_who_paid_is_reimbursed_from_the_payables_page()
    {
        $this->fund('1000.00');
        $payer = $this->activeMember();
        $purchase = $this->recordPurchase($this->activeMember(), ['paid_by' => 'member', 'paid_by_member_id' => $payer->id]);
        $treasurer = $this->staff(Permission::LedgerReimburse);

        $this->actingAs($treasurer)
            ->get(route('payables.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('payables.0.member_name', $payer->full_name)
                ->where('payables.0.paid_by', 'member')
                ->where('payables.0.outstanding', '350.00'));

        $this->actingAs($treasurer)
            ->post(route('payables.reimburse', $purchase->payable), ['amount' => '350.00'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($purchase->payable->fresh()->isOpen());
        $this->assertFundMatchesLedger('650.00');
    }

    public function test_switching_to_paid_by_member_gives_the_money_back_to_the_fund()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $this->actingAs($member->user)
            ->put(route('grocery.update', $purchase), $this->purchaseForm(['paid_by' => 'member', 'paid_by_member_id' => $member->id]))
            ->assertSessionHasNoErrors();

        $purchase->refresh();
        $this->assertSame(GroceryPaidBy::Member, $purchase->paid_by);
        $this->assertNull($purchase->expenseEntry);
        $this->assertSame('350.00', LedgerEntry::query()->where('type', LedgerEntryType::Reversal)->sole()->amount);
        $this->assertSame('350.00', $purchase->payable->amount);
        $this->assertTrue($purchase->payable->member->is($member));
        $this->assertFundMatchesLedger('1000.00');
    }

    public function test_switching_to_paid_by_fund_pays_from_the_fund_as_far_as_it_reaches()
    {
        $this->fund('100.00');
        $member = $this->activeMember();
        $other = $this->activeMember();
        $purchase = $this->recordPurchase($member, ['paid_by' => 'member', 'paid_by_member_id' => $other->id]);

        $this->actingAs($this->staff(Permission::GroceryUpdateAny, Permission::GroceryCreateForOthers))
            ->put(route('grocery.update', $purchase), $this->purchaseForm(['paid_by' => 'fund']))
            ->assertSessionHasNoErrors();

        $purchase->refresh();
        $this->assertSame(GroceryPaidBy::Fund, $purchase->paid_by);
        $this->assertNull($purchase->paid_by_member_id);
        $this->assertSame('100.00', $purchase->expenseEntry->amount);

        // The shortfall is now owed to the buyer, as for any fund purchase.
        $this->assertSame('250.00', $purchase->payable->amount);
        $this->assertTrue($purchase->payable->member->is($member));
        $this->assertFundMatchesLedger('0.00');
    }

    public function test_an_edit_that_leaves_paid_by_out_keeps_who_paid()
    {
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member, ['paid_by' => 'member', 'paid_by_member_id' => $member->id]);

        $this->actingAs($member->user)
            ->put(route('grocery.update', $purchase), $this->purchaseForm(['notes' => 'From the corner shop']))
            ->assertSessionHasNoErrors();

        $purchase->refresh();
        $this->assertSame(GroceryPaidBy::Member, $purchase->paid_by);
        $this->assertSame($member->id, $purchase->paid_by_member_id);
        $this->assertSame('350.00', $purchase->payable->amount);
    }

    public function test_editing_the_total_of_a_member_paid_purchase_changes_what_they_are_owed()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member, ['paid_by' => 'member', 'paid_by_member_id' => $member->id]);

        $this->actingAs($member->user)->put(route('grocery.update', $purchase), $this->purchaseForm([
            'paid_by' => 'member',
            'paid_by_member_id' => $member->id,
            'items' => [['item_name' => 'Rice', 'quantity' => '5', 'unit' => 'kg', 'unit_price' => '100.00']],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('500.00', $purchase->payable->fresh()->amount);
        $this->assertSame(0, LedgerEntry::query()->where('type', '!=', LedgerEntryType::Adjustment)->count());
        $this->assertFundMatchesLedger('1000.00');
    }

    public function test_who_paid_cannot_change_once_money_has_been_reimbursed()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member, ['paid_by' => 'member', 'paid_by_member_id' => $member->id]);
        $this->actingAs($this->staff(Permission::LedgerReimburse))->post(route('payables.reimburse', $purchase->payable), ['amount' => '100.00']);
        $manager = $this->staff(Permission::GroceryUpdateAny, Permission::GroceryCreateForOthers);

        $this->actingAs($manager)
            ->put(route('grocery.update', $purchase), $this->purchaseForm(['paid_by' => 'fund']))
            ->assertSessionHasErrors('paid_by');

        $this->actingAs($manager)
            ->put(route('grocery.update', $purchase), $this->purchaseForm(['paid_by' => 'member', 'paid_by_member_id' => $this->activeMember()->id]))
            ->assertSessionHasErrors('paid_by');

        $this->assertSame(GroceryPaidBy::Member, $purchase->fresh()->paid_by);
        $this->assertFundMatchesLedger('900.00');
    }

    public function test_voiding_a_member_paid_purchase_drops_the_payable_and_returns_reimbursements()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member, ['paid_by' => 'member', 'paid_by_member_id' => $member->id]);
        $this->actingAs($this->staff(Permission::LedgerReimburse))->post(route('payables.reimburse', $purchase->payable), ['amount' => '100.00']);

        $this->actingAs($this->staff(Permission::GroceryDeleteAny))
            ->post(route('grocery.void', $purchase), ['reason' => 'Not for the mess.'])
            ->assertSessionHasNoErrors();

        $payable = $purchase->payable->fresh();
        $this->assertSame('0.00', $payable->amount);
        $this->assertSame('0.00', $payable->amount_reimbursed);
        $this->assertFundMatchesLedger('1000.00');
    }

    public function test_pages_show_who_paid_and_a_payer_has_financial_records()
    {
        $buyer = $this->activeMember();
        $payer = $this->activeMember();
        $purchase = $this->recordPurchase($buyer, ['paid_by' => 'member', 'paid_by_member_id' => $payer->id]);

        $this->actingAs($buyer->user)
            ->get(route('grocery.show', $purchase))
            ->assertInertia(fn (Assert $page) => $page
                ->where('purchase.paid_by', 'member')
                ->where('purchase.paid_by_name', $payer->full_name)
                ->where('purchase.payable.member_name', $payer->full_name));

        $this->actingAs($this->staff(Permission::GroceryCreateForOthers))
            ->get(route('grocery.create'))
            ->assertInertia(fn (Assert $page) => $page->has('payers', 2));

        $this->assertTrue($payer->hasFinancialRecords());
    }
}
