<?php

namespace Tests\Feature\Ledger;

use App\Actions\Ledger\PostAdjustment;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\Activity;
use App\Models\LedgerEntry;
use App\Models\MessSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsLedgerBalance;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class OpeningBalanceTest extends TestCase
{
    use AssertsLedgerBalance, RefreshDatabase, SeedsAccessControl;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-18 12:00:00');
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'mess_name' => 'Green House Mess',
            'mess_address' => null,
            'opening_balance' => '1500.50',
            'fund_start_date' => '2026-10-01',
            'grocery_edit_window_minutes' => 30,
            'members_can_view_balance' => true,
            'max_upload_kb' => 5120,
            'cost_split' => 'equal',
            ...$overrides,
        ];
    }

    private function save(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)->put(route('mess-settings.update'), $this->payload($overrides));
    }

    public function test_saving_an_opening_balance_posts_it_on_the_fund_start_date()
    {
        $this->save()->assertSessionHasNoErrors();

        $entry = LedgerEntry::query()->sole();
        $this->assertSame(LedgerEntryType::OpeningBalance, $entry->type);
        $this->assertSame(LedgerDirection::Credit, $entry->direction);
        $this->assertSame('1500.50', $entry->amount);
        $this->assertSame('2026-10-01', $entry->entry_date->toDateString());
        $this->assertSame($this->admin->id, $entry->created_by);
        $this->assertFundMatchesLedger('1500.50');

        $activity = Activity::query()->where('description', 'opening_balance_posted')->sole();
        $this->assertSame('ledger', $activity->log_name);
        $this->assertSame('0.00', $activity->attribute_changes['old']['balance']);
        $this->assertSame('1500.50', $activity->attribute_changes['attributes']['balance']);
        $this->assertSame('1500.50', $activity->attribute_changes['attributes']['opening_balance']);
    }

    public function test_a_zero_opening_balance_posts_nothing()
    {
        $this->save(['opening_balance' => '0.00'])->assertSessionHasNoErrors();

        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertFundMatchesLedger('0.00');
    }

    public function test_an_opening_balance_needs_a_fund_start_date()
    {
        $this->save(['fund_start_date' => null])
            ->assertSessionHasErrors(['fund_start_date' => 'Set the fund start date to record an opening balance.']);

        $this->assertSame('0.00', MessSetting::current()->opening_balance);
        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_saving_unrelated_settings_posts_nothing_new()
    {
        $this->save();
        $this->save(['grocery_edit_window_minutes' => 45])->assertSessionHasNoErrors();

        $this->assertSame(1, LedgerEntry::query()->count());
        $this->assertSame(1, Activity::query()->where('description', 'opening_balance_posted')->count());
    }

    public function test_before_other_activity_a_change_replaces_the_entry_through_a_reversal()
    {
        $this->save();
        $original = LedgerEntry::query()->sole();

        $this->save(['opening_balance' => '2000.00', 'fund_start_date' => '2026-09-01'])->assertSessionHasNoErrors();

        $reversal = LedgerEntry::query()->where('reverses_entry_id', $original->id)->sole();
        $this->assertSame(LedgerDirection::Debit, $reversal->direction);
        $this->assertSame('1500.50', $reversal->amount);
        $this->assertSame('2026-10-01', $reversal->entry_date->toDateString());

        $replacement = LedgerEntry::query()->where('type', LedgerEntryType::OpeningBalance)->latest('id')->first();
        $this->assertSame('2000.00', $replacement->amount);
        $this->assertSame('2026-09-01', $replacement->entry_date->toDateString());
        $this->assertFundMatchesLedger('2000.00');

        // Changed again, and then back to zero: still allowed.
        $this->save(['opening_balance' => '0'])->assertSessionHasNoErrors();
        $this->assertSame(4, LedgerEntry::query()->count());
        $this->assertFundMatchesLedger('0.00');
        $this->assertFalse(LedgerEntry::hasActivityBeyondOpeningBalance());
    }

    public function test_once_the_ledger_has_activity_the_opening_balance_and_start_date_are_locked()
    {
        $this->save();
        app(PostAdjustment::class)->handle($this->admin, [
            'direction' => LedgerDirection::Credit,
            'amount' => '10.00',
            'entry_date' => '2026-10-05',
            'reason' => 'Found cash',
        ]);

        $locked = 'This is locked because the ledger has activity. Correct the fund with a manual adjustment.';

        $this->save(['opening_balance' => '9000.00'])->assertSessionHasErrors(['opening_balance' => $locked]);
        $this->save(['fund_start_date' => '2026-09-01'])->assertSessionHasErrors(['fund_start_date' => $locked]);

        $settings = MessSetting::current();
        $this->assertSame('1500.50', $settings->opening_balance);
        $this->assertSame('2026-10-01', $settings->fund_start_date?->toDateString());
        $this->assertFundMatchesLedger('1510.50');

        // Other settings can still be saved.
        $this->save(['grocery_edit_window_minutes' => 20])->assertSessionHasNoErrors();
        $this->assertSame(20, MessSetting::current()->grocery_edit_window_minutes);
    }

    public function test_the_settings_page_mirrors_the_lock()
    {
        $this->actingAs($this->admin)
            ->get(route('mess-settings.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('can.changeOpeningBalance', true));

        $this->save();

        $this->actingAs($this->admin)
            ->get(route('mess-settings.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('can.changeOpeningBalance', true));

        app(PostAdjustment::class)->handle($this->admin, [
            'direction' => LedgerDirection::Debit,
            'amount' => '0.50',
            'entry_date' => '2026-10-05',
            'reason' => 'Rounding',
        ]);

        $this->actingAs($this->admin)
            ->get(route('mess-settings.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('can.changeOpeningBalance', false));
    }
}
