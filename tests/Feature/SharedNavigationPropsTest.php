<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedNavigationPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_pages_share_permissions_counts_and_fund_balance(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                // A user without roles holds nothing.
                ->where('auth.permissions', [])
                ->where('nav.counts.pendingApprovals', null)
                ->where('nav.counts.openPayables', null)
                ->where('fund.balance', null)
                ->missing('sidebarOpen'));
    }

    public function test_signed_in_pages_share_the_users_effective_permissions(): void
    {
        $this->seed(AccessControlSeeder::class);

        $this->actingAs(User::factory()->withPermissions(Permission::UserView, Permission::RoleView)->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', fn ($permissions) => collect($permissions)->sort()->values()->all() === ['role.view', 'user.view']));
    }

    public function test_guest_pages_share_no_permissions(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user', null)
                ->where('auth.permissions', []));
    }
}
