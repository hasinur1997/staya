<?php

namespace Tests\Feature\Reports;

use App\Concerns\BusinessCalendar;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\MessSetting;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class MonthClosingTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(BusinessCalendar::today()->setDate(2026, 11, 18)->setTime(12, 0));
        MessSetting::current()->update(['fund_start_date' => '2026-09-01']);
    }

    private function closer(): User
    {
        return User::factory()->withPermissions(Permission::ReportViewAll, Permission::ReportCloseMonth)->create();
    }

    private function close(User $user, string $month = '2026-10')
    {
        return $this->actingAs($user)->post(route('reports.close'), ['month' => $month]);
    }

    public function test_closing_a_finished_month_records_who_and_when_and_is_audited()
    {
        $closer = $this->closer();

        $this->close($closer)->assertSessionHasNoErrors();

        $closure = MonthClosure::query()->sole();
        $this->assertSame('2026-10-01', $closure->month->toDateString());
        $this->assertSame($closer->id, $closure->closed_by);
        $this->assertNull($closure->reopened_at);

        $log = Activity::query()->where('description', 'month_closed')->sole();
        $this->assertSame($closer->id, $log->causer_id);
        $this->assertSame('closed', $log->attribute_changes['attributes']['status']);
    }

    public function test_the_report_shows_the_closed_status_and_what_can_be_done()
    {
        $closer = $this->closer();

        $this->actingAs($closer)->get(route('reports.index', ['month' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.closure.status', 'open')->where('can.close', true)->where('can.reopen', false));

        $this->close($closer);

        $this->actingAs($closer)->get(route('reports.index', ['month' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.closure.status', 'closed')->where('report.closure.closed_by', $closer->name)
                ->where('can.close', false)->where('can.reopen', true));

        $this->actingAs($closer)->get(route('reports.index'))
            ->assertInertia(fn (Assert $page) => $page->where('can.close', false)->where('can.reopen', false));
    }

    public function test_the_current_or_a_future_month_and_an_already_closed_month_cannot_be_closed()
    {
        $closer = $this->closer();

        $this->close($closer, '2026-11')->assertSessionHasErrors('month');
        $this->close($closer, '2027-01')->assertSessionHasErrors('month');
        $this->close($closer)->assertSessionHasNoErrors();
        $this->close($closer)->assertSessionHasErrors('month');

        $this->assertSame(1, MonthClosure::query()->count());
    }

    public function test_a_closed_month_refuses_new_deposits_purchases_and_adjustments()
    {
        $member = Member::factory()->active()->create();
        $staff = User::factory()->withPermissions(Permission::LedgerAdjust, Permission::LedgerViewTransactions)->create();
        $this->close($this->closer());

        $this->actingAs($member->user)->post(route('deposits.store'), ['amount' => '100.00', 'deposit_date' => '2026-10-15'])
            ->assertSessionHasErrors('deposit_date');
        $this->actingAs($member->user)->post(route('grocery.store'), [
            'purchase_date' => '2026-10-15',
            'items' => [['item_name' => 'Rice', 'quantity' => '1', 'unit' => 'kg', 'unit_price' => '80.00']],
        ])->assertSessionHasErrors('purchase_date');
        $this->actingAs($staff)->post(route('ledger.adjustments.store'), ['direction' => 'credit', 'amount' => '50.00', 'entry_date' => '2026-10-15', 'reason' => 'Late'])
            ->assertSessionHasErrors('entry_date');

        $this->assertSame(0, Deposit::query()->count());
    }

    public function test_reopening_needs_a_reason_and_allows_changes_again()
    {
        $member = Member::factory()->active()->create();
        $closer = $this->closer();
        $this->close($closer);

        $this->actingAs($closer)->post(route('reports.reopen'), ['month' => '2026-10'])->assertSessionHasErrors('reason');
        $this->assertNull(MonthClosure::query()->sole()->reopened_at);

        $this->actingAs($closer)->post(route('reports.reopen'), ['month' => '2026-10', 'reason' => 'A receipt was missed.'])
            ->assertSessionHasNoErrors();

        $closure = MonthClosure::query()->sole();
        $this->assertSame('A receipt was missed.', $closure->reopen_reason);
        $this->assertSame($closer->id, $closure->reopened_by);

        $log = Activity::query()->where('description', 'month_reopened')->sole();
        $this->assertSame('A receipt was missed.', $log->attribute_changes['attributes']['reopen_reason']);

        $this->actingAs($member->user)->post(route('deposits.store'), ['amount' => '100.00', 'deposit_date' => '2026-10-15'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Deposit::query()->count());

        // Closing again reuses the same row.
        $this->close($closer)->assertSessionHasNoErrors();
        $this->assertSame(1, MonthClosure::query()->count());
        $this->assertNull(MonthClosure::query()->sole()->reopened_at);
        $this->assertSame(2, Activity::query()->where('description', 'month_closed')->count());
    }

    public function test_a_month_that_is_not_closed_cannot_be_reopened()
    {
        $this->actingAs($this->closer())->post(route('reports.reopen'), ['month' => '2026-10', 'reason' => 'Because.'])
            ->assertSessionHasErrors('month');
    }

    public function test_closing_and_reopening_need_report_close_month()
    {
        $viewer = User::factory()->withPermissions(Permission::ReportViewAll)->create();
        MonthClosure::factory()->forMonth('2026-09-01')->create();

        $this->close($viewer)->assertForbidden();
        $this->actingAs($viewer)->post(route('reports.reopen'), ['month' => '2026-09', 'reason' => 'Please.'])->assertForbidden();

        $this->assertSame(0, Activity::query()->where('description', 'like', 'month_%')->count());
        $this->assertNull(MonthClosure::query()->sole()->reopened_at);
        $this->assertSame(0, MonthClosure::query()->where('month', '2026-10-01')->count());
    }

    public function test_the_report_warns_about_pending_deposits_in_a_month_before_closing()
    {
        $member = Member::factory()->active()->create();
        Deposit::factory()->create(['member_id' => $member->id, 'deposit_date' => '2026-10-20']);

        $this->actingAs($this->closer())->get(route('reports.index', ['month' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page->where('report.unapproved.pending.count', 1));
    }
}
