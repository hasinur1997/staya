<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UpcomingFeature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class UpcomingFeatureTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('upcoming', UpcomingFeature::AuditLog))->assertRedirect(route('login'));
    }

    public function test_every_upcoming_screen_shows_its_coming_soon_page_to_permitted_users()
    {
        foreach (UpcomingFeature::cases() as $feature) {
            foreach ($feature->permissions() as $permission) {
                $this->actingAs(User::factory()->withPermissions($permission)->create())
                    ->get(route('upcoming', $feature))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->component('upcoming')
                        ->where('feature.path', $feature->value)
                        ->where('feature.title', $feature->title())
                        ->has('feature.highlights', 3));
            }
        }
    }

    public function test_users_without_a_screens_permission_get_a_403()
    {
        $user = User::factory()->withPermissions(Permission::LedgerViewBalance)->create();

        foreach (UpcomingFeature::cases() as $feature) {
            $this->actingAs($user)->get(route('upcoming', $feature))->assertForbidden();
        }
    }

    public function test_unknown_paths_are_not_found()
    {
        $this->actingAs(User::factory()->withPermissions(Permission::AuditView)->create())
            ->get('/not-a-feature')
            ->assertNotFound();
    }
}
