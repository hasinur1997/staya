<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LayoutPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_not_available_outside_the_local_environment(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('layout-preview'))
            ->assertNotFound();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->app['env'] = 'local';

        $this->get(route('layout-preview'))->assertRedirect(route('login'));
    }

    public function test_it_previews_the_admin_navigation_by_default(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->create())
            ->get(route('layout-preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('layout-preview')
                ->where('as', 'admin')
                ->where('template', 'dashboard')
                ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('role.view'))
                ->where('nav.counts.pendingApprovals', 3)
                ->where('nav.counts.openPayables', 1)
                ->where('fund.balance', '12450.00'));
    }

    public function test_the_member_preview_hides_staff_counts(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->create())
            ->get(route('layout-preview', ['as' => 'member', 'template' => 'list']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('as', 'member')
                ->where('template', 'list')
                ->where('auth.permissions', fn ($permissions) => ! collect($permissions)->contains('member.view'))
                ->where('nav.counts.pendingApprovals', null)
                ->where('nav.counts.openPayables', null));
    }

    public function test_unknown_query_values_fall_back_to_defaults(): void
    {
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->create())
            ->get(route('layout-preview', ['as' => 'owner', 'template' => 'chart']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('as', 'admin')
                ->where('template', 'dashboard'));
    }
}
