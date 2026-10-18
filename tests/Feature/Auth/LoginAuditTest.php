<?php

namespace Tests\Feature\Auth;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_login_sets_last_login_at_and_is_logged()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        $activity = Activity::query()->where('description', 'login')->sole();
        $this->assertSame('auth', $activity->log_name);
        $this->assertTrue($activity->causer->is($user));
        $this->assertSame('127.0.0.1', $activity->ip_address);
    }

    public function test_a_failed_login_logs_the_email_tried_and_ip()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();

        $activity = Activity::query()->where('description', 'login_failed')->sole();
        $this->assertNull($activity->causer_id);
        $this->assertSame($user->email, $activity->getProperty('email_tried'));
        $this->assertSame('127.0.0.1', $activity->ip_address);
        $this->assertStringNotContainsString('wrong-password', (string) json_encode($activity->properties));
    }

    public function test_an_unknown_email_is_logged_as_typed()
    {
        $this->post(route('login.store'), [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $this->assertSame(
            'nobody@example.com',
            Activity::query()->where('description', 'login_failed')->sole()->getProperty('email_tried'),
        );
    }

    public function test_a_lockout_is_logged()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertTooManyRequests();

        $this->assertGuest();
        $this->assertSame(
            $user->email,
            Activity::query()->where('description', 'lockout')->sole()->getProperty('email_tried'),
        );
    }

    public function test_logout_is_logged()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'));

        $this->assertDatabaseHas('activity_log', [
            'description' => 'logout',
            'causer_type' => 'user',
            'causer_id' => $user->id,
        ]);
    }
}
