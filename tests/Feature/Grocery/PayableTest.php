<?php

namespace Tests\Feature\Grocery;

use App\Enums\GroceryPurchaseStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\BuyerPayable;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WorksWithPurchases;
use Tests\TestCase;

class PayableTest extends TestCase
{
    use RefreshDatabase, WorksWithPurchases;

    private function treasurer(Permission ...$extra): User
    {
        return $this->staff(Permission::LedgerReimburse, ...$extra);
    }

    public function test_a_purchase_larger_than_the_fund_empties_it_and_creates_a_payable_for_the_rest()
    {
        $this->fund('100.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $this->assertSame('350.00', $purchase->grand_total);
        $this->assertSame('100.00', LedgerEntry::query()->where('type', LedgerEntryType::GroceryExpense)->sole()->amount);

        $payable = BuyerPayable::query()->sole();
        $this->assertTrue($payable->purchase->is($purchase));
        $this->assertTrue($payable->member->is($member));
        $this->assertSame('250.00', $payable->amount);
        $this->assertSame('0.00', $payable->amount_reimbursed);
        $this->assertFundMatchesLedger('0.00');

        $changes = Activity::query()->where('description', 'purchase_created')->sole()->attribute_changes;
        $this->assertSame(['amount' => '250.00', 'amount_reimbursed' => '0.00'], $changes['attributes']['payable']);
        $this->assertSame('100.00', $changes['attributes']['fund_paid']);
    }

    public function test_with_an_empty_fund_no_expense_is_posted_and_the_whole_total_is_owed()
    {
        $purchase = $this->recordPurchase($this->activeMember());

        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame('350.00', $purchase->payable->amount);
        $this->assertFundMatchesLedger('0.00');
    }

    public function test_a_reimbursement_posts_a_debit_and_reduces_the_payable()
    {
        $this->fund('100.00');
        $purchase = $this->recordPurchase($this->activeMember());
        $this->fund('500.00', '2026-10-18');
        $treasurer = $this->treasurer();

        $this->actingAs($treasurer)
            ->post(route('payables.reimburse', $purchase->payable), ['amount' => '150.00'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('payables.index'));

        $payable = $purchase->payable->fresh();
        $this->assertSame('150.00', $payable->amount_reimbursed);
        $this->assertSame('100.00', $payable->outstanding());

        $entry = LedgerEntry::query()->where('type', LedgerEntryType::Reimbursement)->sole();
        $this->assertSame(LedgerDirection::Debit, $entry->direction);
        $this->assertSame('150.00', $entry->amount);
        $this->assertSame('2026-10-18', $entry->entry_date->toDateString());
        $this->assertTrue($entry->source->is($payable));
        $this->assertFundMatchesLedger('350.00');

        $activity = Activity::query()->where('description', 'payable_reimbursed')->sole();
        $this->assertSame('ledger', $activity->log_name);
        $this->assertTrue($activity->causer->is($treasurer));
        $this->assertSame('0.00', $activity->attribute_changes['old']['amount_reimbursed']);
        $this->assertSame('150.00', $activity->attribute_changes['attributes']['amount_reimbursed']);
        $this->assertSame('500.00', $activity->attribute_changes['old']['balance']);
        $this->assertSame('350.00', $activity->attribute_changes['attributes']['balance']);
    }

    public function test_a_reimbursement_cannot_exceed_what_is_owed()
    {
        $this->fund('100.00');
        $purchase = $this->recordPurchase($this->activeMember());
        $this->fund('5000.00', '2026-10-18');

        $this->actingAs($this->treasurer())
            ->post(route('payables.reimburse', $purchase->payable), ['amount' => '250.01'])
            ->assertSessionHasErrors(['amount' => 'Only ৳ 250.00 is still owed for this purchase.']);

        $this->assertSame('0.00', $purchase->payable->fresh()->amount_reimbursed);
        $this->assertFundMatchesLedger('5000.00');
    }

    public function test_a_reimbursement_cannot_overdraw_the_fund()
    {
        $this->fund('100.00');
        $purchase = $this->recordPurchase($this->activeMember());
        $this->fund('40.00', '2026-10-18');

        $this->actingAs($this->treasurer())
            ->post(route('payables.reimburse', $purchase->payable), ['amount' => '100.00'])
            ->assertSessionHasErrors(['amount' => 'The fund does not have enough money. ৳ 60.00 is missing.']);

        $this->assertSame('0.00', $purchase->payable->fresh()->amount_reimbursed);
        $this->assertFundMatchesLedger('40.00');
    }

    public function test_a_settled_payable_cannot_be_reimbursed_again()
    {
        $this->fund('100.00');
        $purchase = $this->recordPurchase($this->activeMember());
        $this->fund('1000.00', '2026-10-18');
        $treasurer = $this->treasurer();

        $this->actingAs($treasurer)->post(route('payables.reimburse', $purchase->payable), ['amount' => '250.00'])->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->post(route('payables.reimburse', $purchase->payable), ['amount' => '1.00'])->assertForbidden();

        $this->assertFundMatchesLedger('750.00');
    }

    public function test_reimbursing_needs_ledger_reimburse()
    {
        $purchase = $this->recordPurchase($this->activeMember());
        $manager = $this->staff(Permission::GroceryUpdateAny, Permission::LedgerViewTransactions);

        $this->actingAs($manager)->get(route('payables.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('payables.reimburse', $purchase->payable), ['amount' => '10.00'])->assertForbidden();
    }

    public function test_the_payables_page_lists_open_payables_and_the_sidebar_counts_them()
    {
        $this->fund('100.00');
        $open = $this->recordPurchase($this->activeMember());
        $settled = $this->recordPurchase($this->activeMember());
        $this->fund('1000.00', '2026-10-18');
        $treasurer = $this->treasurer();

        $this->actingAs($treasurer)->post(route('payables.reimburse', $settled->payable), ['amount' => '350.00']);

        $this->actingAs($treasurer)
            ->get(route('payables.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('payables/index')
                ->has('payables', 1)
                ->where('payables.0.grocery_purchase_id', $open->id)
                ->where('payables.0.outstanding', '250.00')
                ->where('payables.0.can.reimburse', true)
                ->where('outstanding', '250.00')
                ->where('nav.counts.openPayables', 1));

        $this->actingAs($open->buyer->user)
            ->get(route('grocery.index'))
            ->assertInertia(fn (Assert $page) => $page->where('nav.counts.openPayables', null));
    }

    public function test_editing_down_reduces_the_unpaid_payable_before_the_fund()
    {
        $this->fund('100.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        // 350 → 300: the 50 comes off the payable; the fund's 100 stays.
        $this->actingAs($member->user)->put(route('grocery.update', $purchase), $this->purchaseForm(['items' => [
            ['item_name' => 'Lentils', 'quantity' => '3', 'unit' => 'kg', 'unit_price' => '100.00'],
        ]]))->assertSessionHasNoErrors();

        $this->assertSame('200.00', $purchase->payable->fresh()->amount);
        $this->assertSame('100.00', $purchase->fresh()->expenseEntry->amount);
        $this->assertSame(0, LedgerEntry::query()->where('type', LedgerEntryType::Reversal)->count());
        $this->assertFundMatchesLedger('0.00');

        // 300 → 60: the payable goes to 0 and the fund gets 40 back.
        $this->actingAs($member->user)->put(route('grocery.update', $purchase), $this->purchaseForm(['items' => [
            ['item_name' => 'Lentils', 'quantity' => '1', 'unit' => 'kg', 'unit_price' => '60.00'],
        ]]))->assertSessionHasNoErrors();

        $this->assertSame('0.00', $purchase->payable->fresh()->amount);
        $this->assertSame('60.00', $purchase->fresh()->expenseEntry->amount);
        $this->assertFundMatchesLedger('40.00');
    }

    public function test_editing_up_takes_from_the_fund_first_and_adds_the_shortfall_to_the_payable()
    {
        $this->fund('100.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);
        $this->fund('30.00', '2026-10-18');

        // 350 → 400: the fund has 30 more to give, the other 20 is owed.
        $this->actingAs($member->user)->put(route('grocery.update', $purchase), $this->purchaseForm(['items' => [
            ['item_name' => 'Rice', 'quantity' => '4', 'unit' => 'kg', 'unit_price' => '100.00'],
        ]]))->assertSessionHasNoErrors();

        $this->assertSame('130.00', $purchase->fresh()->expenseEntry->amount);
        $this->assertSame('270.00', $purchase->payable->fresh()->amount);
        $this->assertFundMatchesLedger('0.00');
    }

    public function test_voiding_drops_the_unpaid_payable_and_returns_the_reimbursed_part_to_the_fund()
    {
        $this->fund('100.00');
        $purchase = $this->recordPurchase($this->activeMember());
        $this->fund('200.00', '2026-10-18');

        $this->actingAs($this->treasurer())->post(route('payables.reimburse', $purchase->payable), ['amount' => '150.00']);
        $this->assertFundMatchesLedger('50.00');

        $this->actingAs($this->staff(Permission::GroceryDeleteAny))
            ->post(route('grocery.void', $purchase), ['reason' => 'Bought for a private party.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(GroceryPurchaseStatus::Voided, $purchase->fresh()->status);

        $payable = $purchase->payable->fresh();
        $this->assertSame('0.00', $payable->amount);
        $this->assertSame('0.00', $payable->amount_reimbursed);
        $this->assertFalse($payable->isOpen());

        // The 100 expense and the 150 reimbursement are both reversed.
        $this->assertSame(2, LedgerEntry::query()->where('type', LedgerEntryType::Reversal)->count());
        $this->assertFundMatchesLedger('300.00');
    }

    public function test_the_buyer_cannot_change_once_they_have_been_reimbursed()
    {
        $this->fund('100.00');
        $purchase = $this->recordPurchase($this->activeMember());
        $this->fund('200.00', '2026-10-18');
        $this->actingAs($this->treasurer())->post(route('payables.reimburse', $purchase->payable), ['amount' => '50.00']);

        $this->actingAs($this->staff(Permission::GroceryUpdateAny, Permission::GroceryCreateForOthers))
            ->put(route('grocery.update', $purchase), $this->purchaseForm(['buyer_member_id' => $this->activeMember()->id]))
            ->assertSessionHasErrors('buyer_member_id');
    }
}
