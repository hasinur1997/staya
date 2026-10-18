<?php

namespace Tests\Feature\Ledger;

use App\Actions\Ledger\PostLedgerEntries;
use App\Actions\Ledger\ReverseLedgerEntry;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\Permission;
use App\Models\LedgerEntry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class LedgerPageTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-18 12:00:00');
    }

    private function postEntry(LedgerEntryType $type, LedgerDirection $direction, string $amount, string $date): LedgerEntry
    {
        return app(PostLedgerEntries::class)->handle(User::factory()->create(), [[
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'entry_date' => $date,
            'reason' => 'Test',
        ]])[0];
    }

    public function test_the_page_requires_ledger_view_transactions()
    {
        $this->actingAs(User::factory()->withPermissions(Permission::LedgerViewBalance, Permission::LedgerAdjust)->create())
            ->get(route('ledger.index'))
            ->assertForbidden();
    }

    public function test_it_lists_the_current_dhaka_month_by_default_with_its_summary()
    {
        $this->postEntry(LedgerEntryType::Deposit, LedgerDirection::Credit, '1000.00', '2026-09-30');
        $deposit = $this->postEntry(LedgerEntryType::Deposit, LedgerDirection::Credit, '500.00', '2026-10-02');
        app(ReverseLedgerEntry::class)->handle(User::factory()->create(), $deposit, 'Counted twice');
        $this->postEntry(LedgerEntryType::GroceryExpense, LedgerDirection::Debit, '120.75', '2026-10-10');

        $this->actingAs(User::factory()->withPermissions(Permission::LedgerViewTransactions)->create())
            ->get(route('ledger.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ledger/index')
                ->where('filters.from', '2026-10-01')
                ->where('filters.to', '2026-10-31')
                ->has('entries.data', 3)
                ->where('entries.data.0.type', 'grocery_expense')
                ->where('entries.data.0.signed_amount', '-120.75')
                ->where('entries.data.2.id', $deposit->id)
                ->where('entries.data.2.reversed_by_entry_id', $deposit->id + 1)
                ->where('entries.data.1.reverses_entry_id', $deposit->id)
                ->where('summary.opening', '1000.00')
                ->where('summary.deposits', '0.00')
                ->where('summary.expenses', '120.75')
                ->where('summary.closing', '879.25')
                ->where('can.adjust', false));
    }

    public function test_it_filters_by_period_type_and_direction()
    {
        $this->postEntry(LedgerEntryType::Deposit, LedgerDirection::Credit, '1000.00', '2026-09-30');
        $this->postEntry(LedgerEntryType::Adjustment, LedgerDirection::Credit, '5.00', '2026-09-15');
        $this->postEntry(LedgerEntryType::Adjustment, LedgerDirection::Debit, '2.00', '2026-09-16');

        $user = User::factory()->withPermissions(Permission::LedgerViewTransactions)->create();

        $this->actingAs($user)
            ->get(route('ledger.index', ['from' => '2026-09-01', 'to' => '2026-09-30', 'type' => 'adjustment', 'direction' => 'debit']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 1)
                ->where('entries.data.0.amount', '2.00')
                ->where('summary.closing', '1003.00'));

        $this->actingAs($user)
            ->get(route('ledger.index', ['from' => '2026-09-30', 'to' => '2026-09-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_a_role_built_in_the_ui_gets_exactly_its_ledger_actions()
    {
        $role = Role::factory()->withPermissions(Permission::LedgerViewTransactions)->create();
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('ledger.index'))->assertOk();
        $this->actingAs($user)->get(route('ledger.adjustments.create'))->assertForbidden();

        $role->givePermissionTo(Permission::LedgerAdjust->value);
        $user->refresh();

        $this->actingAs($user)
            ->get(route('ledger.index'))
            ->assertInertia(fn (Assert $page) => $page->where('can.adjust', true));
    }
}
