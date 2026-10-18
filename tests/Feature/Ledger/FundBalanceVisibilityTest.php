<?php

namespace Tests\Feature\Ledger;

use App\Actions\Ledger\PostLedgerEntries;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\Permission;
use App\Models\MessSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class FundBalanceVisibilityTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        app(PostLedgerEntries::class)->handle(User::factory()->create(), [[
            'type' => LedgerEntryType::Adjustment,
            'direction' => LedgerDirection::Credit,
            'amount' => '12450.00',
            'entry_date' => '2026-10-01',
            'reason' => 'Test',
        ]]);
    }

    private function assertSharedBalance(User $user, ?string $expected): void
    {
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('fund.balance', $expected));
    }

    public function test_the_balance_is_not_sent_without_ledger_view_balance()
    {
        $this->assertSharedBalance(User::factory()->withPermissions(Permission::GroceryView)->create(), null);
    }

    public function test_the_balance_is_sent_with_ledger_view_balance_while_the_setting_is_on()
    {
        $this->assertSharedBalance(User::factory()->withPermissions(Permission::LedgerViewBalance)->create(), '12450.00');
    }

    public function test_turning_the_setting_off_hides_the_balance_from_members_but_not_staff()
    {
        MessSetting::current()->update(['members_can_view_balance' => false]);

        $this->assertSharedBalance(User::factory()->withPermissions(Permission::LedgerViewBalance)->create(), null);
        $this->assertSharedBalance(
            User::factory()->withPermissions(Permission::LedgerViewBalance, Permission::LedgerViewTransactions)->create(),
            '12450.00',
        );
    }
}
