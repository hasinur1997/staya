<?php

namespace Tests\Feature\Reports;

use App\Actions\Reports\CalculateMonthlyReport;
use App\Models\Deposit;
use App\Models\GroceryPurchase;
use App\Models\Member;
use App\Models\MemberStay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class CalculateMonthlyReportTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private function member(string $from = '2026-09-01', ?string $to = null): Member
    {
        $member = Member::factory()->active(withStay: false)->create();
        MemberStay::factory()->for($member)->between($from, $to)->create();

        return $member;
    }

    private function purchase(Member $buyer, string $total, string $date = '2026-10-10'): GroceryPurchase
    {
        return GroceryPurchase::factory()->create(['buyer_member_id' => $buyer->id, 'grand_total' => $total, 'purchase_date' => $date]);
    }

    private function deposit(Member $member, string $amount, string $date = '2026-10-05'): Deposit
    {
        return Deposit::factory()->approved()->create(['member_id' => $member->id, 'amount' => $amount, 'deposit_date' => $date]);
    }

    private function report(int $month = 10, int $year = 2026)
    {
        return app(CalculateMonthlyReport::class)->handle($year, $month);
    }

    public function test_the_share_and_each_members_due_or_credit_match_a_worked_fixture()
    {
        $members = collect(range(1, 8))->map(fn () => $this->member());
        $this->purchase($members[0], '24000.00');
        $this->deposit($members[0], '4000.00');
        $this->deposit($members[1], '1000.00');
        $this->deposit($members[1], '2000.00');

        $report = $this->report();

        $this->assertSame('24000.00', $report->expenses);
        $this->assertSame('7000.00', $report->deposits);
        $this->assertSame(8, $report->activeMembers);
        $this->assertSame('3000.00', $report->share);
        $this->assertSame('0.00', $report->leftover);
        $this->assertSame('1000.00', $report->rowFor($members[0]->id)['due_credit']);
        $this->assertSame('0.00', $report->rowFor($members[1]->id)['due_credit']);
        $this->assertSame('-3000.00', $report->rowFor($members[2]->id)['due_credit']);
    }

    public function test_the_leftover_paisa_is_reported_when_the_share_does_not_divide_evenly()
    {
        $members = collect(range(1, 3))->map(fn () => $this->member());
        $this->purchase($members[0], '100.00');

        $report = $this->report();

        $this->assertSame('33.33', $report->share);
        $this->assertSame('0.01', $report->leftover);
    }

    public function test_another_months_data_does_not_change_this_month()
    {
        $member = $this->member('2026-08-01');
        $this->purchase($member, '600.00');
        $this->deposit($member, '1000.00');
        $before = $this->report();

        $this->purchase($member, '9000.00', '2026-09-20');
        $this->purchase($member, '9000.00', '2026-11-02');
        $this->deposit($member, '5000.00', '2026-09-28');
        $this->deposit($member, '5000.00', '2026-11-03');

        $this->assertEquals($before, $this->report());
        $this->assertSame('400.00', $this->report()->rowFor($member->id)['due_credit']);
    }

    public function test_a_deposit_dated_in_october_but_approved_in_november_counts_in_october()
    {
        $member = $this->member();
        $deposit = $this->deposit($member, '700.00', '2026-10-30');
        $deposit->forceFill(['reviewed_at' => '2026-11-02 09:00:00'])->save();

        $this->assertSame('700.00', $this->report(10)->deposits);
        $this->assertSame('0.00', $this->report(11)->deposits);
    }

    public function test_only_approved_deposits_and_active_purchases_count()
    {
        $member = $this->member();
        $this->deposit($member, '500.00');
        Deposit::factory()->create(['member_id' => $member->id, 'amount' => '900.00', 'deposit_date' => '2026-10-06']);
        Deposit::factory()->rejected()->create(['member_id' => $member->id, 'amount' => '800.00', 'deposit_date' => '2026-10-06']);
        Deposit::factory()->cancelled()->create(['member_id' => $member->id, 'amount' => '700.00', 'deposit_date' => '2026-10-06']);
        $this->purchase($member, '300.00');
        GroceryPurchase::factory()->voided()->create(['buyer_member_id' => $member->id, 'grand_total' => '999.00', 'purchase_date' => '2026-10-11']);

        $report = $this->report();

        $this->assertSame('500.00', $report->deposits);
        $this->assertSame('300.00', $report->expenses);
    }

    public function test_a_purchase_a_payable_partly_covered_counts_in_full()
    {
        $member = $this->member();
        GroceryPurchase::factory()->settled()->create(['buyer_member_id' => $member->id, 'grand_total' => '500.00', 'purchase_date' => '2026-10-10']);

        $this->assertSame('500.00', $this->report()->expenses);
        $this->assertSame('500.00', $this->report()->share);
    }

    public function test_a_rejoined_member_counts_only_in_months_overlapping_a_stay()
    {
        $member = Member::factory()->active(withStay: false)->create();
        MemberStay::factory()->for($member)->between('2026-06-01', '2026-07-15')->create();
        MemberStay::factory()->for($member)->between('2026-09-10')->create();

        $active = fn (int $month): bool => $this->report($month)->rowFor($member->id) !== null;

        $this->assertTrue($active(6));
        $this->assertTrue($active(7));
        $this->assertFalse($active(8));
        $this->assertTrue($active(9));
        $this->assertTrue($active(10));
    }

    public function test_a_month_without_members_has_no_share()
    {
        $report = $this->report(1, 2026);

        $this->assertSame(0, $report->activeMembers);
        $this->assertSame('0.00', $report->share);
        $this->assertSame('0.00', $report->leftover);
    }
}
