<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditLog;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-18 12:00:00');
    }

    private function auditor(): User
    {
        return User::factory()->withPermissions(Permission::AuditView)->create();
    }

    public function test_users_without_audit_view_get_403_and_guests_are_sent_to_login()
    {
        $activity = Activity::factory()->create();
        $user = User::factory()->withPermissions(Permission::LedgerViewTransactions, Permission::ReportViewAll)->create();

        $this->actingAs($user)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($user)->get(route('audit.show', $activity))->assertForbidden();

        auth()->logout();
        $this->get(route('audit.index'))->assertRedirect(route('login'));
    }

    public function test_the_log_lists_entries_newest_first_with_actor_ip_and_dhaka_ready_times()
    {
        $actor = User::factory()->create(['name' => 'Rafi Ahmed']);
        Activity::factory()->create(['description' => 'purchase_created', 'log_name' => 'grocery', 'causer_id' => $actor->id, 'ip_address' => '203.0.113.9', 'created_at' => '2026-10-10 03:00:00']);
        Activity::factory()->create(['description' => 'deposit_approved', 'log_name' => 'deposit', 'causer_id' => $actor->id, 'created_at' => '2026-10-12 03:00:00']);

        $this->actingAs($this->auditor())->get(route('audit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('audit/index')
                ->has('activities.data', 2)
                ->where('activities.data.0.action', 'deposit_approved')
                ->where('activities.data.0.action_label', 'Deposit approved')
                ->where('activities.data.1.actor_name', 'Rafi Ahmed')
                ->where('activities.data.1.ip_address', '203.0.113.9')
                ->where('activities.data.1.created_at', '2026-10-10T03:00:00+00:00'));
    }

    public function test_the_default_range_is_the_last_thirty_days_in_asia_dhaka()
    {
        Activity::factory()->create(['description' => 'login', 'created_at' => '2026-09-18 17:59:00']);
        Activity::factory()->create(['description' => 'logout', 'created_at' => '2026-09-18 18:01:00']);

        $this->actingAs($this->auditor())->get(route('audit.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.from', '2026-09-19')
                ->where('filters.to', '2026-10-18')
                ->has('activities.data', 1)
                ->where('activities.data.0.action', 'logout'));
    }

    public function test_entries_can_be_filtered_by_date_actor_log_action_and_subject()
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        Activity::factory()->create(['description' => 'purchase_created', 'log_name' => 'grocery', 'causer_id' => $a->id, 'subject_type' => 'grocery_purchase', 'subject_id' => 7, 'created_at' => '2026-10-05 05:00:00']);
        Activity::factory()->create(['description' => 'deposit_approved', 'log_name' => 'deposit', 'causer_id' => $b->id, 'subject_type' => 'deposit', 'subject_id' => 3, 'created_at' => '2026-10-06 05:00:00']);
        Activity::factory()->create(['description' => 'deposit_approved', 'log_name' => 'deposit', 'causer_id' => $a->id, 'subject_type' => 'deposit', 'subject_id' => 4, 'created_at' => '2026-10-07 05:00:00']);
        $viewer = $this->auditor();

        $ids = fn (array $query) => $this->actingAs($viewer)->get(route('audit.index', ['from' => '2026-10-01', 'to' => '2026-10-31', ...$query]))
            ->viewData('page')['props']['activities']['data'];
        $count = fn (array $query) => count($ids($query));

        $this->assertSame(3, $count([]));
        $this->assertSame(2, $count(['actor' => $a->id]));
        $this->assertSame(2, $count(['log' => 'deposit']));
        $this->assertSame(2, $count(['action' => 'deposit_approved']));
        $this->assertSame(1, $count(['subject' => 'grocery_purchase']));
        $this->assertSame(1, $count(['subject' => 'deposit', 'subject_id' => 4]));
        $this->assertSame(1, $count(['from' => '2026-10-06', 'to' => '2026-10-06']));
        $this->assertSame(0, $count(['actor' => $b->id, 'log' => 'grocery']));
    }

    public function test_invalid_filters_are_rejected()
    {
        $viewer = $this->auditor();

        $this->actingAs($viewer)->get(route('audit.index', ['log' => 'nonsense']))->assertSessionHasErrors('log');
        $this->actingAs($viewer)->get(route('audit.index', ['from' => '2026-10-20', 'to' => '2026-10-01']))->assertSessionHasErrors('to');
        $this->actingAs($viewer)->get(route('audit.index', ['actor' => 999999]))->assertSessionHasErrors('actor');
    }

    public function test_failed_logins_are_listed_with_the_email_tried_and_ip()
    {
        $this->post(route('login.store'), ['email' => 'intruder@example.com', 'password' => 'guess']);

        $this->actingAs($this->auditor())->get(route('audit.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activities.data.0.action', 'login_failed')
                ->where('activities.data.0.log_name', AuditLog::Auth->value)
                ->where('activities.data.0.email_tried', 'intruder@example.com')
                ->where('activities.data.0.actor_name', null)
                ->whereType('activities.data.0.ip_address', 'string'));
    }

    public function test_self_approvals_are_flagged()
    {
        $deposit = Deposit::factory()->approved()->create();
        Activity::factory()->withChanges(['status' => 'pending'], ['status' => 'approved', 'is_self_approved' => true])
            ->create(['description' => 'deposit_approved', 'log_name' => 'deposit', 'subject_type' => 'deposit', 'subject_id' => $deposit->id]);
        Activity::factory()->withChanges(['status' => 'pending'], ['status' => 'approved', 'is_self_approved' => false])
            ->create(['description' => 'deposit_approved', 'log_name' => 'deposit']);

        $this->actingAs($this->auditor())->get(route('audit.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activities.data.0.self_approved', false)
                ->where('activities.data.1.self_approved', true));
    }

    public function test_an_entry_shows_before_and_after_values_ip_and_a_link_to_its_subject()
    {
        $deposit = Deposit::factory()->create();
        $activity = Activity::factory()->withChanges(['status' => 'pending', 'note' => null], ['status' => 'approved', 'is_self_approved' => true, 'items' => [['name' => 'Rice']]])
            ->create(['description' => 'deposit_approved', 'log_name' => 'deposit', 'subject_type' => 'deposit', 'subject_id' => $deposit->id, 'ip_address' => '198.51.100.4']);
        $viewer = User::factory()->withPermissions(Permission::AuditView, Permission::DepositViewAll)->create();

        $this->actingAs($viewer)->get(route('audit.show', $activity))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('audit/show')
                ->where('activity.ip_address', '198.51.100.4')
                ->where('activity.diff.0', ['field' => 'status', 'old' => 'pending', 'new' => 'approved'])
                ->where('activity.diff.1', ['field' => 'note', 'old' => null, 'new' => null])
                ->where('activity.diff.2', ['field' => 'is_self_approved', 'old' => null, 'new' => 'true'])
                ->where('activity.diff.3.new', '[{"name":"Rice"}]')
                ->where('activity.subject_url', route('deposits.show', $deposit)));

        // Without the right to open the subject there is no link.
        $this->actingAs($this->auditor())->get(route('audit.show', $activity))
            ->assertInertia(fn (Assert $page) => $page->where('activity.subject_url', null));
    }

    public function test_the_viewer_offers_no_way_to_change_or_remove_entries()
    {
        $activity = Activity::factory()->create();
        $viewer = $this->auditor();
        $path = route('audit.show', $activity, false);

        $this->actingAs($viewer)->put($path)->assertMethodNotAllowed();
        $this->actingAs($viewer)->patch($path)->assertMethodNotAllowed();
        $this->actingAs($viewer)->delete($path)->assertMethodNotAllowed();
        $this->actingAs($viewer)->post(route('audit.index', [], false))->assertMethodNotAllowed();

        $this->assertSame(1, Activity::query()->count());
    }
}
