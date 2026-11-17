<?php

namespace Tests\Feature\Grocery;

use App\Enums\Permission;
use App\Models\GroceryPurchase;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WorksWithPurchases;
use Tests\TestCase;

class PurchasePagesTest extends TestCase
{
    use RefreshDatabase, WorksWithPurchases;

    public function test_every_member_sees_every_purchase_with_month_and_range_filters()
    {
        $this->fund('5000.00', '2026-09-01');
        $buyer = $this->activeMember();
        $october = $this->recordPurchase($buyer);
        $september = $this->recordPurchase($buyer, ['purchase_date' => '2026-09-20']);
        $viewer = $this->activeMember();

        $this->actingAs($viewer->user)
            ->get(route('grocery.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('purchases.data', 2)
                ->where('purchases.data.0.id', $october->id)
                ->where('purchases.data.0.buyer_name', $buyer->full_name)
                ->where('purchases.data.0.item_names', ['Rice', 'Eggs'])
                ->where('purchases.data.0.can.update', false)
                ->where('total', '700.00')
                ->where('can.create', true));

        $this->actingAs($viewer->user)
            ->get(route('grocery.index', ['month' => '2026-09']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('purchases.data', 1)
                ->where('purchases.data.0.id', $september->id)
                ->where('total', '350.00'));

        $this->actingAs($viewer->user)
            ->get(route('grocery.index', ['from' => '2026-10-01', 'to' => '2026-10-31', 'buyer' => $viewer->id]))
            ->assertInertia(fn (Assert $page) => $page->has('purchases.data', 0)->where('total', '0.00'));
    }

    public function test_the_detail_page_shows_items_and_the_payable()
    {
        $this->fund('100.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $this->actingAs($this->activeMember()->user)
            ->get(route('grocery.show', $purchase))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('grocery/show')
                ->where('purchase.grand_total', '350.00')
                ->has('purchase.items', 2)
                ->where('purchase.items.0.line_total', '200.00')
                ->where('purchase.items.0.unit_label', 'kg')
                ->where('purchase.payable.outstanding', '250.00')
                ->where('purchase.is_own', false)
                ->where('purchase.locked', false)
                ->missing('purchase.receipt_path'));

        $this->actingAs(User::factory()->create())->get(route('grocery.show', $purchase))->assertForbidden();
    }

    public function test_the_form_pages_offer_buyers_only_to_staff_who_record_for_others()
    {
        $member = $this->activeMember();
        Member::factory()->inactive()->create();

        $this->actingAs($member->user)
            ->get(route('grocery.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('grocery/create')
                ->where('today', '2026-10-18')
                ->where('ownMemberId', $member->id)
                ->where('buyers', [])
                ->has('units', 6)
                ->where('can.chooseBuyer', false));

        $this->actingAs($this->staff(Permission::GroceryCreateForOthers))
            ->get(route('grocery.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('ownMemberId', null)
                ->has('buyers', 1)
                ->where('can.chooseBuyer', true));
    }

    public function test_item_names_are_suggested_from_earlier_purchases()
    {
        $member = $this->activeMember();
        $this->recordPurchase($member);
        $this->recordPurchase($member);

        $this->actingAs($member->user)
            ->getJson(route('grocery.item-names', ['q' => 'ri']))
            ->assertOk()
            ->assertExactJson(['names' => ['Rice']]);

        $this->actingAs($member->user)
            ->getJson(route('grocery.item-names'))
            ->assertExactJson(['names' => ['Eggs', 'Rice']]);

        $this->actingAs($this->staff())->getJson(route('grocery.item-names'))->assertForbidden();
    }

    public function test_the_totals_preview_is_computed_on_the_server_and_skips_incomplete_lines()
    {
        $member = $this->activeMember();

        $this->actingAs($member->user)
            ->postJson(route('grocery.totals'), ['items' => [
                ['quantity' => '2.5', 'unit_price' => '80.00', 'line_total' => '1.00'],
                ['quantity' => '', 'unit_price' => '12.50'],
                ['quantity' => '0.125', 'unit_price' => '33'],
            ]])
            ->assertOk()
            ->assertExactJson(['line_totals' => ['200.00', null, '4.13'], 'grand_total' => '204.13']);

        $this->assertSame(0, GroceryPurchase::query()->count());
        $this->actingAs($this->staff())->postJson(route('grocery.totals'), ['items' => []])->assertForbidden();
    }

    public function test_a_member_profile_lists_the_purchases_they_made()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);
        $this->actingAs($member->user)->post(route('grocery.void', $this->recordPurchase($member)));

        $this->actingAs($member->user)
            ->get(route('members.show', $member))
            ->assertInertia(fn (Assert $page) => $page
                ->has('purchases', 1)
                ->where('purchases.0.id', $purchase->id));
    }

    public function test_a_member_with_purchases_has_financial_records()
    {
        $member = $this->activeMember();
        $this->assertFalse($member->hasFinancialRecords());

        GroceryPurchase::factory()->voided()->create(['buyer_member_id' => $member->id]);

        $this->assertTrue($member->hasFinancialRecords());
    }
}
