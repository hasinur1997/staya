<?php

namespace Tests\Feature\Deposits;

use App\Enums\Permission;
use App\Exceptions\ImmutableRecord;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WorksWithDeposits;
use Tests\TestCase;

class DepositRecordTest extends TestCase
{
    use RefreshDatabase, WorksWithDeposits;

    public function test_there_is_no_route_to_edit_or_delete_a_deposit()
    {
        foreach (['deposits.edit', 'deposits.update', 'deposits.destroy'] as $name) {
            $this->assertFalse(Route::has($name), "[{$name}] must not exist.");
        }
    }

    public function test_an_approved_deposits_amount_and_date_cannot_change()
    {
        $deposit = Deposit::factory()->approved()->create();

        foreach (['amount' => '1.00', 'deposit_date' => '2026-10-01', 'member_id' => Member::factory()->create()->id] as $field => $value) {
            try {
                $deposit->fresh()->update([$field => $value]);
                $this->fail("Changing [{$field}] should be refused.");
            } catch (ImmutableRecord) {
                $this->assertTrue(true);
            }
        }

        $this->expectException(ImmutableRecord::class);
        $deposit->delete();
    }

    public function test_members_see_only_their_own_deposits()
    {
        $member = $this->activeMember();
        $own = Deposit::factory()->create(['member_id' => $member->id]);
        $other = Deposit::factory()->create();

        $this->actingAs($member->user)
            ->get(route('deposits.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('deposits/index')
                ->has('deposits.data', 1)
                ->where('deposits.data.0.id', $own->id)
                ->where('can.viewAll', false));

        $this->actingAs($member->user)->get(route('deposits.show', $own))->assertOk();
        $this->actingAs($member->user)->get(route('deposits.show', $other))->assertForbidden();
    }

    public function test_view_all_lists_everyone_with_filters()
    {
        Deposit::factory()->create(['deposit_date' => '2026-10-01']);
        $september = Deposit::factory()->create(['deposit_date' => '2026-09-25']);
        Deposit::factory()->approved()->create(['deposit_date' => '2026-10-02']);

        $this->actingAs($this->reviewer())
            ->get(route('deposits.index'))
            ->assertInertia(fn (Assert $page) => $page->has('deposits.data', 3)->where('can.viewAll', true));

        $this->actingAs($this->reviewer())
            ->get(route('deposits.index', ['month' => '2026-09']))
            ->assertInertia(fn (Assert $page) => $page->has('deposits.data', 1)->where('deposits.data.0.id', $september->id));

        $this->actingAs($this->reviewer())
            ->get(route('deposits.index', ['status' => 'approved']))
            ->assertInertia(fn (Assert $page) => $page->has('deposits.data', 1));
    }

    public function test_the_deposit_list_requires_a_deposit_view_permission()
    {
        $this->actingAs(User::factory()->create())->get(route('deposits.index'))->assertForbidden();
    }

    public function test_the_member_profile_shows_deposit_history_to_those_allowed()
    {
        $member = $this->activeMember();
        Deposit::factory()->count(2)->create(['member_id' => $member->id]);

        $this->actingAs($member->user)
            ->get(route('members.show', $member))
            ->assertInertia(fn (Assert $page) => $page->has('deposits', 2));

        $this->actingAs(User::factory()->withPermissions(Permission::MemberView)->create())
            ->get(route('members.show', $member))
            ->assertInertia(fn (Assert $page) => $page->where('deposits', null));
    }

    public function test_deleting_a_member_with_deposits_keeps_the_deposit()
    {
        $deposit = Deposit::factory()->cancelled()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('members.destroy', $deposit->member))
            ->assertRedirect(route('members.index'));

        $this->assertSoftDeleted($deposit->member);
        $this->assertModelExists($deposit);
    }
}
