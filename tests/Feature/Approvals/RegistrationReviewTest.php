<?php

namespace Tests\Feature\Approvals;

use App\Concerns\BusinessCalendar;
use App\Enums\MemberStatus;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Member;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class RegistrationReviewTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(BusinessCalendar::today()->setDate(2026, 10, 18)->setTime(12, 0));
    }

    public function test_the_queue_requires_member_approve_registration()
    {
        Member::factory()->create();

        $this->actingAs(User::factory()->withPermissions(Permission::MemberView)->create())
            ->get(route('approvals.registrations.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('approvals.registrations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('approvals/registrations/index')
                ->has('registrations', 1)
                ->where('nav.counts.pendingApprovals', 1));
    }

    public function test_the_approvals_entry_point_leads_reviewers_to_registrations()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('approvals.index'))
            ->assertRedirect(route('approvals.registrations.index'));

        $this->actingAs(User::factory()->withPermissions(Permission::DepositApprove)->create())
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('upcoming'));
    }

    public function test_approval_activates_the_member_assigns_the_role_and_opens_a_stay()
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->create();

        $this->actingAs($admin)
            ->post(route('approvals.registrations.approve', $member), ['joined_on' => '2026-10-15'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('approvals.registrations.index'));

        $member->refresh();

        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertTrue($member->user->hasRole('Member'));
        $this->assertTrue($member->reviewer->is($admin));
        $this->assertSame('2026-10-15', $member->currentStay->joined_on->toDateString());

        $activity = Activity::query()->where('description', 'registration_approved')->sole();
        $this->assertTrue($activity->causer->is($admin));
        $this->assertSame('pending', $activity->attribute_changes['old']['status']);
        $this->assertSame('active', $activity->attribute_changes['attributes']['status']);
    }

    public function test_a_registration_is_approved_only_once()
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->create();

        $this->actingAs($admin)->post(route('approvals.registrations.approve', $member), ['joined_on' => '2026-10-15']);
        $this->actingAs($admin)
            ->post(route('approvals.registrations.approve', $member), ['joined_on' => '2026-10-16'])
            ->assertForbidden();

        $this->assertCount(1, $member->stays()->get());
        $this->assertSame(1, Activity::query()->where('description', 'registration_approved')->count());
    }

    public function test_approval_needs_the_permission()
    {
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->withPermissions(Permission::MemberView)->create())
            ->post(route('approvals.registrations.approve', $member), ['joined_on' => '2026-10-15'])
            ->assertForbidden();

        $this->assertSame(MemberStatus::Pending, $member->fresh()->status);
    }

    public function test_a_reviewer_cannot_grant_a_role_with_permissions_they_lack()
    {
        $member = Member::factory()->create();
        $reviewer = User::factory()->withPermissions(Permission::MemberApproveRegistration)->create();

        $this->actingAs($reviewer)
            ->post(route('approvals.registrations.approve', $member), ['joined_on' => '2026-10-15'])
            ->assertForbidden();

        $this->assertFalse($member->user->fresh()->hasRole('Member'));
    }

    public function test_the_join_date_cannot_be_in_the_future_or_a_closed_month()
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->create();
        MonthClosure::factory()->forMonth('2026-09-01')->create();

        $this->actingAs($admin)
            ->post(route('approvals.registrations.approve', $member), ['joined_on' => '2026-10-19'])
            ->assertSessionHasErrors(['joined_on' => 'The date cannot be in the future.']);

        $this->actingAs($admin)
            ->post(route('approvals.registrations.approve', $member), ['joined_on' => '2026-08-20'])
            ->assertSessionHasErrors(['joined_on' => 'September 2026 is closed. Reopen it before changing anything dated in it.']);

        $this->assertSame(MemberStatus::Pending, $member->fresh()->status);
    }

    public function test_rejection_requires_a_reason_and_shows_it_to_the_applicant()
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->create();

        $this->actingAs($admin)
            ->post(route('approvals.registrations.reject', $member), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->post(route('approvals.registrations.reject', $member), ['reason' => 'The NID copy is unreadable.'])
            ->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertSame(MemberStatus::Rejected, $member->status);
        $this->assertCount(0, $member->user->roles);

        $this->actingAs($member->user)
            ->get(route('registration.status'))
            ->assertInertia(fn (Assert $page) => $page->where('reason', 'The NID copy is unreadable.'));

        $activity = Activity::query()->where('description', 'registration_rejected')->sole();
        $this->assertSame('The NID copy is unreadable.', $activity->attribute_changes['attributes']['rejection_reason']);
    }

    public function test_rejection_needs_the_permission()
    {
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->withPermissions(Permission::MemberView)->create())
            ->post(route('approvals.registrations.reject', $member), ['reason' => 'No'])
            ->assertForbidden();
    }
}
