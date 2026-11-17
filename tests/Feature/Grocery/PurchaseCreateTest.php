<?php

namespace Tests\Feature\Grocery;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\BuyerPayable;
use App\Models\GroceryPurchase;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\MessSetting;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WorksWithPurchases;
use Tests\TestCase;

class PurchaseCreateTest extends TestCase
{
    use RefreshDatabase, WorksWithPurchases;

    public function test_a_multi_item_purchase_saves_with_server_totals_and_reduces_the_balance_once()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();

        $this->actingAs($member->user)
            ->post(route('grocery.store'), $this->purchaseForm(['notes' => 'Weekly bazaar']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('grocery.show', GroceryPurchase::query()->sole()));

        $purchase = GroceryPurchase::query()->sole();
        $this->assertSame('350.00', $purchase->grand_total);
        $this->assertSame('2026-10-17', $purchase->purchase_date->toDateString());
        $this->assertTrue($purchase->buyer->is($member));
        $this->assertSame($member->user_id, $purchase->created_by);
        $this->assertSame('Weekly bazaar', $purchase->notes);
        $this->assertSame(['200.00', '150.00'], $purchase->items->pluck('line_total')->all());
        $this->assertSame(['2.500', '12.000'], $purchase->items->pluck('quantity')->all());

        $expense = LedgerEntry::query()->where('type', LedgerEntryType::GroceryExpense)->sole();
        $this->assertSame(LedgerDirection::Debit, $expense->direction);
        $this->assertSame('350.00', $expense->amount);
        $this->assertSame('2026-10-17', $expense->entry_date->toDateString());
        $this->assertTrue($expense->source->is($purchase));
        $this->assertSame(0, BuyerPayable::query()->count());
        $this->assertFundMatchesLedger('650.00');
    }

    public function test_totals_sent_by_the_client_are_ignored()
    {
        $this->fund('1000.00');
        $form = $this->purchaseForm(['grand_total' => '1.00']);
        $form['items'][0]['line_total'] = '0.01';
        $form['items'][1]['line_total'] = '99999.00';

        $this->actingAs($this->activeMember()->user)->post(route('grocery.store'), $form)->assertSessionHasNoErrors();

        $purchase = GroceryPurchase::query()->sole();
        $this->assertSame('350.00', $purchase->grand_total);
        $this->assertSame(['200.00', '150.00'], $purchase->items->pluck('line_total')->all());
        $this->assertFundMatchesLedger('650.00');
    }

    public function test_line_totals_round_half_up_to_two_places()
    {
        $this->fund('1000.00');

        $this->actingAs($this->activeMember()->user)->post(route('grocery.store'), $this->purchaseForm(['items' => [
            ['item_name' => 'Chilli', 'quantity' => '0.125', 'unit' => 'kg', 'unit_price' => '33.00'],
            ['item_name' => 'Salt', 'quantity' => '0.333', 'unit' => 'kg', 'unit_price' => '10.00'],
        ]]))->assertSessionHasNoErrors();

        // 4.125 rounds up to 4.13; 3.33 stays.
        $purchase = GroceryPurchase::query()->sole();
        $this->assertSame(['4.13', '3.33'], $purchase->items->pluck('line_total')->all());
        $this->assertSame('7.46', $purchase->grand_total);
    }

    public function test_quantity_and_unit_price_must_be_greater_than_zero()
    {
        $member = $this->activeMember();

        foreach (['0', '-1', '1.2345', 'abc', ''] as $quantity) {
            $form = $this->purchaseForm();
            $form['items'][0]['quantity'] = $quantity;

            $this->actingAs($member->user)->post(route('grocery.store'), $form)->assertSessionHasErrors('items.0.quantity');
        }

        foreach (['0', '0.00', '-5', '1.234', '1e3', ''] as $unitPrice) {
            $form = $this->purchaseForm();
            $form['items'][1]['unit_price'] = $unitPrice;

            $this->actingAs($member->user)->post(route('grocery.store'), $form)->assertSessionHasErrors('items.1.unit_price');
        }

        $this->assertSame(0, GroceryPurchase::query()->count());
    }

    public function test_a_purchase_needs_at_least_one_item_with_a_name_and_a_known_unit()
    {
        $member = $this->activeMember();

        $this->actingAs($member->user)->post(route('grocery.store'), $this->purchaseForm(['items' => []]))->assertSessionHasErrors('items');

        $form = $this->purchaseForm();
        $form['items'][0]['item_name'] = '';
        $form['items'][1]['unit'] = 'bucket';

        $this->actingAs($member->user)->post(route('grocery.store'), $form)->assertSessionHasErrors(['items.0.item_name', 'items.1.unit']);
    }

    public function test_a_line_that_rounds_to_zero_is_rejected()
    {
        $this->actingAs($this->activeMember()->user)->post(route('grocery.store'), $this->purchaseForm(['items' => [
            ['item_name' => 'Dust', 'quantity' => '0.001', 'unit' => 'gm', 'unit_price' => '0.01'],
        ]]))->assertSessionHasErrors('items.0.unit_price');

        $this->assertSame(0, GroceryPurchase::query()->count());
    }

    public function test_future_dated_purchases_are_rejected()
    {
        $this->actingAs($this->activeMember()->user)
            ->post(route('grocery.store'), $this->purchaseForm(['purchase_date' => '2026-10-19']))
            ->assertSessionHasErrors(['purchase_date' => 'The date cannot be in the future.']);
    }

    public function test_purchases_before_the_fund_start_date_are_rejected()
    {
        MessSetting::current()->update(['fund_start_date' => '2026-10-10']);

        $this->actingAs($this->activeMember()->user)
            ->post(route('grocery.store'), $this->purchaseForm(['purchase_date' => '2026-10-09']))
            ->assertSessionHasErrors('purchase_date');
    }

    public function test_purchases_in_a_closed_month_are_rejected()
    {
        MonthClosure::factory()->forMonth('2026-09-01')->create();

        $this->actingAs($this->activeMember()->user)
            ->post(route('grocery.store'), $this->purchaseForm(['purchase_date' => '2026-09-30']))
            ->assertSessionHasErrors('purchase_date');

        $this->assertSame(0, GroceryPurchase::query()->count());
    }

    public function test_a_member_cannot_name_someone_else_as_the_buyer()
    {
        $other = $this->activeMember();

        $this->actingAs($this->activeMember()->user)
            ->post(route('grocery.store'), $this->purchaseForm(['buyer_member_id' => $other->id]))
            ->assertForbidden();

        $this->assertSame(0, GroceryPurchase::query()->count());
    }

    public function test_staff_with_create_for_others_choose_any_active_member_as_buyer()
    {
        $this->fund('1000.00');
        $buyer = $this->activeMember();
        $staff = $this->staff(Permission::GroceryCreateForOthers);

        $this->actingAs($staff)
            ->post(route('grocery.store'), $this->purchaseForm(['buyer_member_id' => $buyer->id]))
            ->assertSessionHasNoErrors();

        $purchase = GroceryPurchase::query()->sole();
        $this->assertTrue($purchase->buyer->is($buyer));
        $this->assertSame($staff->id, $purchase->created_by);
    }

    public function test_the_buyer_must_be_an_active_member()
    {
        $staff = $this->staff(Permission::GroceryCreateForOthers);
        $inactive = Member::factory()->inactive()->create();

        $this->actingAs($staff)
            ->post(route('grocery.store'), $this->purchaseForm(['buyer_member_id' => $inactive->id]))
            ->assertSessionHasErrors('buyer_member_id');

        $this->actingAs($staff)
            ->post(route('grocery.store'), $this->purchaseForm())
            ->assertSessionHasErrors('buyer_member_id');

        $this->assertSame(0, GroceryPurchase::query()->count());
    }

    public function test_an_inactive_member_gets_a_403_creating_a_purchase()
    {
        $inactive = Member::factory()->inactive()->create();

        $this->actingAs($inactive->user)->get(route('grocery.index'))->assertOk();
        $this->actingAs($inactive->user)->get(route('grocery.create'))->assertForbidden();
        $this->actingAs($inactive->user)->post(route('grocery.store'), $this->purchaseForm())->assertForbidden();

        $this->assertSame(0, GroceryPurchase::query()->count());
    }

    public function test_recording_needs_grocery_create()
    {
        $viewer = $this->staff();

        $this->actingAs($viewer)->get(route('grocery.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('grocery.store'), $this->purchaseForm())->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('grocery.index'))->assertForbidden();
    }

    public function test_a_receipt_is_stored_privately_and_served_only_to_viewers()
    {
        Storage::fake('uploads');
        $this->fund('1000.00');
        $member = $this->activeMember();

        $this->actingAs($member->user)
            ->post(route('grocery.store'), $this->purchaseForm(['receipt' => UploadedFile::fake()->image('receipt.jpg')]))
            ->assertSessionHasNoErrors();

        $purchase = GroceryPurchase::query()->sole();
        $this->assertNotNull($purchase->receipt_path);
        Storage::disk('uploads')->assertExists($purchase->receipt_path);

        $this->actingAs($member->user)->get(route('grocery.receipt', $purchase))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('grocery.receipt', $purchase))->assertForbidden();
    }

    public function test_a_receipt_of_the_wrong_type_is_rejected_and_nothing_is_left_behind()
    {
        Storage::fake('uploads');

        $this->actingAs($this->activeMember()->user)
            ->post(route('grocery.store'), $this->purchaseForm(['receipt' => UploadedFile::fake()->create('receipt.exe', 10)]))
            ->assertSessionHasErrors('receipt');

        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_creating_a_purchase_is_audit_logged_with_items_and_balances()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $activity = Activity::query()->where('description', 'purchase_created')->sole();
        $this->assertSame('grocery', $activity->log_name);
        $this->assertTrue($activity->causer->is($member->user));
        $this->assertTrue($activity->subject->is($purchase));
        $this->assertSame('1000.00', $activity->attribute_changes['old']['balance']);
        $this->assertSame('650.00', $activity->attribute_changes['attributes']['balance']);
        $this->assertSame('350.00', $activity->attribute_changes['attributes']['grand_total']);
        $this->assertSame(['Rice', 'Eggs'], array_column($activity->attribute_changes['attributes']['items'], 'item_name'));
        $this->assertNotNull($activity->ip_address);
    }
}
