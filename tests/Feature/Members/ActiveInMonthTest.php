<?php

namespace Tests\Feature\Members;

use App\Concerns\BusinessCalendar;
use App\Models\Member;
use App\Models\MemberStay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class ActiveInMonthTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    /**
     * @return list<int>
     */
    private function activeIds(int $year, int $month): array
    {
        return Member::query()->activeInMonth($year, $month)->orderBy('id')->pluck('id')->all();
    }

    public function test_a_member_who_left_and_rejoined_is_active_only_in_months_of_a_stay()
    {
        $member = Member::factory()->active(withStay: false)->create();
        MemberStay::factory()->for($member)->between('2026-07-01', '2026-09-14')->create();
        MemberStay::factory()->for($member)->between('2026-11-20')->create();

        $this->assertSame([], $this->activeIds(2026, 6));
        $this->assertSame([$member->id], $this->activeIds(2026, 7));
        $this->assertSame([$member->id], $this->activeIds(2026, 9));
        $this->assertSame([], $this->activeIds(2026, 10));
        $this->assertSame([$member->id], $this->activeIds(2026, 11));
        $this->assertSame([$member->id], $this->activeIds(2027, 3));
    }

    public function test_one_day_at_either_end_of_a_month_counts_as_active()
    {
        $leftOnFirst = Member::factory()->active(withStay: false)->create();
        MemberStay::factory()->for($leftOnFirst)->between('2026-08-15', '2026-10-01')->create();

        $joinedOnLast = Member::factory()->active(withStay: false)->create();
        MemberStay::factory()->for($joinedOnLast)->between('2026-10-31')->create();

        $this->assertSame([$leftOnFirst->id, $joinedOnLast->id], $this->activeIds(2026, 10));
        $this->assertSame([$leftOnFirst->id], $this->activeIds(2026, 9));
        $this->assertSame([$joinedOnLast->id], $this->activeIds(2026, 11));
    }

    public function test_months_are_compared_on_dhaka_business_dates()
    {
        // 23:30 on 31 Oct in Dhaka is still 31 Oct; 00:30 on 1 Nov is November.
        $this->travelTo('2026-10-31 17:30:00');
        $this->assertSame('2026-10-31', BusinessCalendar::today()->toDateString());

        $this->travelTo('2026-10-31 18:30:00');
        $this->assertSame('2026-11-01', BusinessCalendar::today()->toDateString());

        $member = Member::factory()->active(withStay: false)->create();
        MemberStay::factory()->for($member)->between(BusinessCalendar::today()->toDateString())->create();

        $this->assertSame([], $this->activeIds(2026, 10));
        $this->assertSame([$member->id], $this->activeIds(2026, 11));
    }

    public function test_applicants_with_no_stays_are_never_active()
    {
        Member::factory()->create();
        Member::factory()->rejected()->create();

        $this->assertSame([], $this->activeIds(2026, 10));
    }
}
