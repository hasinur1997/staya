<?php

namespace Tests\Feature\Members;

use App\Concerns\BusinessCalendar;
use App\Enums\MemberStatus;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Member;
use App\Models\MemberStay;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class MemberStayTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(BusinessCalendar::today()->setDate(2026, 10, 18)->setTime(12, 0));
        $this->admin = User::factory()->admin()->create();
    }

    private function memberSince(string $joinedOn): Member
    {
        $member = Member::factory()->active(withStay: false)->create();
        MemberStay::factory()->for($member)->between($joinedOn)->create();

        return $member;
    }

    public function test_deactivating_closes_the_open_stay_and_keeps_the_account_active()
    {
        $member = $this->memberSince('2026-01-10');

        $this->actingAs($this->admin)
            ->post(route('members.deactivate', $member), ['left_on' => '2026-10-15'])
            ->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertSame(MemberStatus::Inactive, $member->status);
        $this->assertNull($member->currentStay);
        $this->assertSame('2026-10-15', $member->stays()->sole()->left_on->toDateString());
        $this->assertTrue($member->user->is_active);

        $activity = Activity::query()->where('description', 'member_deactivated')->sole();
        $this->assertNull($activity->attribute_changes['old']['stays'][0]['left_on']);
        $this->assertSame('2026-10-15', $activity->attribute_changes['attributes']['stays'][0]['left_on']);
    }

    public function test_reactivating_opens_a_new_stay_and_leaves_past_stays_alone()
    {
        $member = Member::factory()->inactive()->create();
        $past = $member->stays()->sole();
        $pastValues = $past->only(['joined_on', 'left_on', 'updated_at']);

        $this->actingAs($this->admin)
            ->post(route('members.reactivate', $member), ['joined_on' => '2026-10-18'])
            ->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertCount(2, $member->stays()->get());
        $this->assertSame('2026-10-18', $member->currentStay->joined_on->toDateString());
        $this->assertEquals($pastValues, $past->fresh()->only(['joined_on', 'left_on', 'updated_at']));
        $this->assertSame(1, Activity::query()->where('description', 'member_reactivated')->count());
    }

    public function test_a_rejoin_must_start_after_the_last_stay_ended()
    {
        $member = Member::factory()->inactive()->create();
        $leftOn = $member->stays()->sole()->left_on->toDateString();

        $this->actingAs($this->admin)
            ->post(route('members.reactivate', $member), ['joined_on' => $leftOn])
            ->assertSessionHasErrors('joined_on');

        $this->assertSame(MemberStatus::Inactive, $member->fresh()->status);
        $this->assertCount(1, $member->stays()->get());
    }

    public function test_a_leave_date_cannot_be_before_the_stay_started_or_in_the_future()
    {
        $member = $this->memberSince('2026-10-10');

        $this->actingAs($this->admin)
            ->post(route('members.deactivate', $member), ['left_on' => '2026-10-09'])
            ->assertSessionHasErrors(['left_on' => 'The leave date cannot be before the stay started (2026-10-10).']);

        $this->actingAs($this->admin)
            ->post(route('members.deactivate', $member), ['left_on' => '2026-10-19'])
            ->assertSessionHasErrors(['left_on' => 'The date cannot be in the future.']);

        $this->assertSame(MemberStatus::Active, $member->fresh()->status);
    }

    public function test_a_stay_change_that_would_alter_a_closed_month_is_refused()
    {
        $member = $this->memberSince('2026-01-10');
        MonthClosure::factory()->forMonth('2026-09-01')->create();

        // Leaving in August would drop the member from closed September.
        $this->actingAs($this->admin)
            ->post(route('members.deactivate', $member), ['left_on' => '2026-08-31'])
            ->assertSessionHasErrors(['left_on' => 'September 2026 is closed. Reopen it before changing anything dated in it.']);

        $this->actingAs($this->admin)
            ->post(route('members.deactivate', $member), ['left_on' => '2026-10-01'])
            ->assertSessionHasNoErrors();
    }

    public function test_an_inactive_member_cannot_be_deactivated_again()
    {
        $member = Member::factory()->inactive()->create();

        $this->actingAs($this->admin)
            ->post(route('members.deactivate', $member), ['left_on' => '2026-10-18'])
            ->assertForbidden();
    }

    public function test_an_active_member_cannot_be_reactivated_into_a_second_open_stay()
    {
        $member = $this->memberSince('2026-01-10');

        $this->actingAs($this->admin)
            ->post(route('members.reactivate', $member), ['joined_on' => '2026-10-18'])
            ->assertForbidden();

        $this->assertCount(1, $member->stays()->get());
    }

    public function test_deactivating_and_reactivating_require_member_deactivate()
    {
        $active = $this->memberSince('2026-01-10');
        $inactive = Member::factory()->inactive()->create();
        $actor = User::factory()->withPermissions(Permission::MemberView, Permission::MemberUpdate)->create();

        $this->actingAs($actor)
            ->post(route('members.deactivate', $active), ['left_on' => '2026-10-18'])
            ->assertForbidden();

        $this->actingAs($actor)
            ->post(route('members.reactivate', $inactive), ['joined_on' => '2026-10-18'])
            ->assertForbidden();
    }
}
