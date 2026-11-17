<?php

namespace Tests\Feature\Audit;

use App\Concerns\BusinessCalendar;
use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\WorksWithPurchases;
use Tests\TestCase;

/**
 * Sweeps the audit list from the project overview: every action the app
 * can log is known to the viewer, is logged somewhere in the code, and
 * shows up in the audit log with actor and IP.
 */
class AuditCompletenessTest extends TestCase
{
    use RefreshDatabase, WorksWithPurchases;

    /**
     * Every action name passed to `->log(...)` in app/.
     *
     * @return array<int, string>
     */
    private function loggedActions(): array
    {
        $found = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/->log\(([^;]*?)\);/s', (string) file_get_contents($file->getPathname()), $calls);

            foreach ($calls[1] as $arguments) {
                preg_match_all("/'([a-z_]+)'/", $arguments, $names);
                array_push($found, ...$names[1]);
            }
        }

        return array_values(array_unique($found));
    }

    public function test_every_logged_action_is_a_known_audit_action()
    {
        $unknown = array_diff($this->loggedActions(), array_column(AuditAction::cases(), 'value'));

        $this->assertSame([], array_values($unknown), 'Add these logged actions to App\Enums\AuditAction.');
    }

    public function test_every_known_audit_action_is_logged_somewhere()
    {
        $missing = array_diff(array_column(AuditAction::cases(), 'value'), $this->loggedActions());

        $this->assertSame([], array_values($missing), 'These actions are never logged.');
    }

    public function test_the_viewer_lists_one_entry_for_every_action_with_a_label_actor_and_ip()
    {
        $actor = User::factory()->create();
        $this->travelTo('2026-10-18 12:00:00');

        foreach (AuditAction::cases() as $action) {
            Activity::factory()->create(['description' => $action->value, 'causer_id' => $actor->id, 'ip_address' => '192.0.2.1']);
        }

        $auditor = User::factory()->withPermissions(Permission::AuditView)->create();

        $this->actingAs($auditor)->get(route('audit.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('activities.data', count(AuditAction::cases()))
                ->has('actions', count(AuditAction::cases()))
                ->where('activities.data', fn ($rows) => collect($rows)->every(
                    fn ($row) => $row['actor_name'] === $actor->name
                        && $row['ip_address'] === '192.0.2.1'
                        && $row['action_label'] !== $row['action']
                        && ! str_contains($row['action_label'], '_'),
                )));

        $this->assertCount(count(AuditAction::cases()), array_unique(array_column(AuditAction::cases(), 'value')));
    }

    public function test_financial_changes_made_in_the_app_appear_with_actor_ip_and_before_after_values()
    {
        $this->fund('1000.00');
        $member = $this->activeMember();
        $purchase = $this->recordPurchase($member);

        $this->actingAs($member->user)->put(route('grocery.update', $purchase), $this->purchaseForm([
            'items' => [['item_name' => 'Rice', 'quantity' => '5', 'unit' => 'kg', 'unit_price' => '80.00']],
        ]))->assertSessionHasNoErrors();
        $this->actingAs($member->user)->post(route('grocery.void', $purchase))->assertSessionHasNoErrors();

        $closer = User::factory()->withPermissions(Permission::ReportCloseMonth)->create();
        $this->travelTo(BusinessCalendar::today()->setDate(2026, 11, 18)->setTime(12, 0));
        $this->actingAs($closer)->post(route('reports.close'), ['month' => '2026-10'])->assertSessionHasNoErrors();
        $this->actingAs($closer)->post(route('reports.reopen'), ['month' => '2026-10', 'reason' => 'Check'])->assertSessionHasNoErrors();

        $auditor = User::factory()->withPermissions(Permission::AuditView)->create();
        $this->actingAs($auditor)->get(route('audit.index', ['from' => '2026-10-01', 'to' => '2026-11-30']))
            ->assertInertia(fn (Assert $page) => $page->where('activities.data', function ($rows) use ($member, $closer) {
                $rows = collect($rows)->keyBy('action');

                return $rows->has(['purchase_created', 'purchase_updated', 'purchase_voided', 'month_closed', 'month_reopened'])
                    && $rows['purchase_created']['actor_id'] === $member->user->id
                    && $rows['month_closed']['actor_id'] === $closer->id
                    && $rows->every(fn ($row) => $row['ip_address'] !== null && $row['has_changes']);
            }));

        $updated = Activity::query()->where('description', 'purchase_updated')->sole();
        $this->assertSame('350.00', $updated->attribute_changes['old']['grand_total']);
        $this->assertSame('400.00', $updated->attribute_changes['attributes']['grand_total']);
        $this->assertSame(1, MonthClosure::query()->count());
    }
}
