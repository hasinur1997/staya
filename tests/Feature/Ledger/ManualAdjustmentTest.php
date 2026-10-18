<?php

namespace Tests\Feature\Ledger;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\LedgerEntry;
use App\Models\MessSetting;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsLedgerBalance;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class ManualAdjustmentTest extends TestCase
{
    use AssertsLedgerBalance, RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        // 18 Oct 2026, 18:00 in Dhaka.
        $this->travelTo('2026-10-18 12:00:00');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'direction' => 'credit',
            'amount' => '500.00',
            'entry_date' => '2026-10-18',
            'reason' => 'Cash found in the drawer',
            ...$overrides,
        ];
    }

    private function adjuster(): User
    {
        return User::factory()->withPermissions(Permission::LedgerAdjust, Permission::LedgerViewTransactions)->create();
    }

    public function test_the_form_and_posting_require_ledger_adjust()
    {
        $user = User::factory()->withPermissions(Permission::LedgerViewTransactions, Permission::LedgerViewBalance)->create();

        $this->actingAs($user)->get(route('ledger.adjustments.create'))->assertForbidden();
        $this->actingAs($user)->post(route('ledger.adjustments.store'), $this->payload())->assertForbidden();

        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertFundMatchesLedger('0.00');
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->post(route('ledger.adjustments.store'), $this->payload())->assertRedirect(route('login'));
    }

    public function test_the_form_renders_for_an_adjuster()
    {
        MessSetting::current()->update(['fund_start_date' => '2026-10-01']);

        $this->actingAs($this->adjuster())
            ->get(route('ledger.adjustments.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ledger/adjustments/create')
                ->where('today', '2026-10-18')
                ->where('fundStartDate', '2026-10-01'));
    }

    public function test_a_positive_adjustment_is_posted_and_logged()
    {
        $adjuster = $this->adjuster();

        $this->actingAs($adjuster)
            ->post(route('ledger.adjustments.store'), $this->payload(['amount' => '1200.5']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('ledger.index'));

        $entry = LedgerEntry::query()->sole();
        $this->assertSame(LedgerEntryType::Adjustment, $entry->type);
        $this->assertSame(LedgerDirection::Credit, $entry->direction);
        $this->assertSame('1200.50', $entry->amount);
        $this->assertSame('2026-10-18', $entry->entry_date->toDateString());
        $this->assertSame('Cash found in the drawer', $entry->reason);
        $this->assertSame($adjuster->id, $entry->created_by);
        $this->assertFundMatchesLedger('1200.50');

        $activity = Activity::query()->where('description', 'adjustment_posted')->sole();
        $this->assertSame('ledger', $activity->log_name);
        $this->assertSame('ledger_entry', $activity->subject_type);
        $this->assertSame($entry->id, $activity->subject_id);
        $this->assertTrue($activity->causer->is($adjuster));
        $this->assertSame('127.0.0.1', $activity->ip_address);
        $this->assertSame(['balance' => '0.00'], $activity->attribute_changes['old']);
        $this->assertSame('1200.50', $activity->attribute_changes['attributes']['balance']);
        $this->assertSame('1200.50', $activity->attribute_changes['attributes']['amount']);
        $this->assertSame('credit', $activity->attribute_changes['attributes']['direction']);
        $this->assertSame('Cash found in the drawer', $activity->attribute_changes['attributes']['reason']);
    }

    public function test_a_negative_adjustment_within_the_balance_is_posted()
    {
        $adjuster = $this->adjuster();
        $this->actingAs($adjuster)->post(route('ledger.adjustments.store'), $this->payload(['amount' => '500.00']));

        $this->actingAs($adjuster)
            ->post(route('ledger.adjustments.store'), $this->payload(['direction' => 'debit', 'amount' => '120.25', 'reason' => 'Miscounted']))
            ->assertSessionHasNoErrors();

        $this->assertFundMatchesLedger('379.75');
    }

    public function test_a_negative_adjustment_below_zero_is_rejected_with_the_missing_amount()
    {
        $adjuster = $this->adjuster();
        $this->actingAs($adjuster)->post(route('ledger.adjustments.store'), $this->payload(['amount' => '100.00']));

        $this->actingAs($adjuster)
            ->post(route('ledger.adjustments.store'), $this->payload(['direction' => 'debit', 'amount' => '1250.00']))
            ->assertSessionHasErrors(['amount' => 'The fund does not have enough money. ৳ 1,150.00 is missing.']);

        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertSame(1, Activity::query()->where('description', 'adjustment_posted')->count());
        $this->assertFundMatchesLedger('100.00');
    }

    public function test_a_reason_is_required()
    {
        $this->actingAs($this->adjuster())
            ->post(route('ledger.adjustments.store'), $this->payload(['reason' => '   ']))
            ->assertSessionHasErrors(['reason' => 'Give a reason for the adjustment.']);

        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_invalid_amounts_and_directions_are_rejected()
    {
        $adjuster = $this->adjuster();

        foreach (['0', '0.00', '-5', '12.345', 'abc', '1e3', '12345678901.00'] as $amount) {
            $this->actingAs($adjuster)
                ->post(route('ledger.adjustments.store'), $this->payload(['amount' => $amount]))
                ->assertSessionHasErrors('amount');
        }

        $this->actingAs($adjuster)
            ->post(route('ledger.adjustments.store'), $this->payload(['direction' => 'sideways']))
            ->assertSessionHasErrors('direction');

        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_a_future_date_in_dhaka_is_rejected()
    {
        $this->actingAs($this->adjuster())
            ->post(route('ledger.adjustments.store'), $this->payload(['entry_date' => '2026-10-19']))
            ->assertSessionHasErrors(['entry_date' => 'The date cannot be in the future.']);

        // 00:30 on 19 Oct in Dhaka, still 18 Oct in UTC.
        $this->travelTo('2026-10-18 18:30:00');

        $this->actingAs($this->adjuster())
            ->post(route('ledger.adjustments.store'), $this->payload(['entry_date' => '2026-10-19']))
            ->assertSessionHasNoErrors();
    }

    public function test_a_date_before_the_fund_start_is_rejected()
    {
        MessSetting::current()->update(['fund_start_date' => '2026-10-01']);

        $this->actingAs($this->adjuster())
            ->post(route('ledger.adjustments.store'), $this->payload(['entry_date' => '2026-09-30']))
            ->assertSessionHasErrors('entry_date');

        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_a_date_in_a_closed_month_is_rejected_until_it_is_reopened()
    {
        $closure = MonthClosure::factory()->forMonth('2026-09-01')->create();
        $adjuster = $this->adjuster();

        $this->actingAs($adjuster)
            ->post(route('ledger.adjustments.store'), $this->payload(['entry_date' => '2026-09-30']))
            ->assertSessionHasErrors(['entry_date' => 'September 2026 is closed. Reopen it before changing anything dated in it.']);

        $this->assertSame(0, LedgerEntry::query()->count());

        $closure->update(['reopened_by' => $adjuster->id, 'reopened_at' => now(), 'reopen_reason' => 'Late receipt']);

        $this->actingAs($adjuster)
            ->post(route('ledger.adjustments.store'), $this->payload(['entry_date' => '2026-09-30']))
            ->assertSessionHasNoErrors();

        $this->assertFundMatchesLedger('500.00');
    }

    public function test_an_adjuster_without_the_ledger_page_returns_to_the_form()
    {
        $this->actingAs(User::factory()->withPermissions(Permission::LedgerAdjust)->create())
            ->post(route('ledger.adjustments.store'), $this->payload())
            ->assertRedirect(route('ledger.adjustments.create'));
    }
}
