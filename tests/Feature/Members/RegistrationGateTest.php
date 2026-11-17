<?php

namespace Tests\Feature\Members;

use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class RegistrationGateTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    /**
     * Every GET route behind `auth`, with its parameters filled in.
     *
     * @return list<string>
     */
    private function appUrls(Member $member): array
    {
        $parameters = [
            'member' => $member->id,
            'user' => $member->user_id,
            'role' => Role::member()->id,
            'feature' => 'grocery',
            // Records that do not exist still redirect: the gate runs first.
            'deposit' => 999,
        ];

        return collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => in_array('GET', $route->methods(), true)
                && in_array('auth', $route->gatherMiddleware(), true)
                && ! in_array($route->getName(), ['registration.status', 'verification.notice', 'verification.verify'], true))
            ->map(function (Route $route) use ($parameters): string {
                $uri = preg_replace_callback('/\{(\w+)\??}/', fn (array $match) => (string) ($parameters[$match[1]] ?? 1), $route->uri());

                return '/'.ltrim((string) $uri, '/');
            })
            ->values()
            ->all();
    }

    public function test_a_pending_applicant_is_sent_to_the_status_page_from_every_app_route()
    {
        $member = Member::factory()->create();
        $urls = $this->appUrls($member);

        $this->assertContains('/dashboard', $urls);
        $this->assertContains('/members', $urls);
        $this->assertContains('/settings/profile', $urls);

        foreach ($urls as $url) {
            $this->actingAs($member->user)
                ->get($url)
                ->assertRedirect(route('registration.status'));
        }
    }

    public function test_a_rejected_applicant_is_kept_out_and_sees_the_reason()
    {
        $member = Member::factory()->rejected('The NID copy is unreadable.')->create();

        foreach ($this->appUrls($member) as $url) {
            $this->actingAs($member->user)
                ->get($url)
                ->assertRedirect(route('registration.status'));
        }

        $this->actingAs($member->user)
            ->get(route('registration.status'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/registration-status')
                ->where('status', 'rejected')
                ->where('reason', 'The NID copy is unreadable.'));
    }

    public function test_applicants_get_403_on_json_and_form_requests()
    {
        $member = Member::factory()->create();

        $this->actingAs($member->user)
            ->getJson(route('dashboard'))
            ->assertForbidden();

        $this->actingAs($member->user)
            ->patch(route('profile.update'), ['name' => 'Taken over', 'email' => $member->user->email])
            ->assertRedirect(route('registration.status'));

        $this->assertNotSame('Taken over', $member->user->fresh()->name);
    }

    public function test_applicants_can_still_sign_out()
    {
        $member = Member::factory()->create();

        $this->actingAs($member->user)->post(route('logout'))->assertRedirect();

        $this->assertGuest();
    }

    public function test_staff_accounts_without_a_member_profile_are_not_gated()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_approved_and_inactive_members_skip_the_status_page()
    {
        $active = Member::factory()->active()->create();
        $inactive = Member::factory()->inactive()->create();

        $this->actingAs($active->user)->get(route('dashboard'))->assertOk();
        $this->actingAs($inactive->user)->get(route('dashboard'))->assertOk();
        $this->actingAs($active->user)->get(route('registration.status'))->assertRedirect(route('dashboard'));
    }
}
