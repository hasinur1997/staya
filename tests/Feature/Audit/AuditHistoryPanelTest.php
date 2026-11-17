<?php

namespace Tests\Feature\Audit;

use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WorksWithPurchases;
use Tests\TestCase;

class AuditHistoryPanelTest extends TestCase
{
    use RefreshDatabase, WorksWithPurchases;

    public function test_the_purchase_page_shows_its_history_only_with_audit_view()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);
        $auditor = User::factory()->withPermissions(Permission::GroceryView, Permission::AuditView)->create();

        $this->actingAs($auditor)->get(route('grocery.show', $purchase))
            ->assertInertia(fn (Assert $page) => $page
                ->has('history', 1)
                ->where('history.0.action', 'purchase_created')
                ->where('history.0.actor_name', $member->user->name)
                ->whereType('history.0.ip_address', 'string')
                ->where('history.0.has_changes', true));

        $this->actingAs($member->user)->get(route('grocery.show', $purchase))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('history', null));
    }

    public function test_the_deposit_page_shows_its_history_only_with_audit_view()
    {
        $deposit = Deposit::factory()->create();
        Activity::factory()->create(['description' => 'deposit_submitted', 'log_name' => 'deposit', 'subject_type' => 'deposit', 'subject_id' => $deposit->id]);
        Activity::factory()->create(['description' => 'deposit_submitted', 'log_name' => 'deposit', 'subject_type' => 'deposit', 'subject_id' => $deposit->id + 100]);

        $auditor = User::factory()->withPermissions(Permission::DepositViewAll, Permission::AuditView)->create();
        $this->actingAs($auditor)->get(route('deposits.show', $deposit))
            ->assertInertia(fn (Assert $page) => $page->has('history', 1));

        $plain = User::factory()->withPermissions(Permission::DepositViewAll)->create();
        $this->actingAs($plain)->get(route('deposits.show', $deposit))
            ->assertInertia(fn (Assert $page) => $page->where('history', null));
    }

    public function test_the_member_profile_shows_its_history_only_with_audit_view()
    {
        $member = Member::factory()->active()->create();
        Activity::factory()->create(['description' => 'member_updated', 'log_name' => 'member', 'subject_type' => 'member', 'subject_id' => $member->id]);

        $auditor = User::factory()->withPermissions(Permission::MemberView, Permission::AuditView)->create();
        $this->actingAs($auditor)->get(route('members.show', $member))
            ->assertInertia(fn (Assert $page) => $page->has('history', 1));

        $plain = User::factory()->withPermissions(Permission::MemberView)->create();
        $this->actingAs($plain)->get(route('members.show', $member))
            ->assertInertia(fn (Assert $page) => $page->where('history', null));
    }
}
