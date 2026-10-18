<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Tests\TestCase;

class DeactivatedUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deactivated_user_cannot_log_in()
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => 'This account has been deactivated.']);

        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_log', ['description' => 'login_blocked', 'subject_id' => $user->id]);
    }

    public function test_a_wrong_password_for_a_deactivated_user_gives_the_usual_error()
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_an_existing_session_is_ended_once_the_user_is_deactivated()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_deactivated_user_cannot_log_in_with_a_passkey()
    {
        $passkey = new Passkey;
        $passkey->setRelation('user', User::factory()->inactive()->create());

        $this->assertFalse(Passkeys::allowsLogin(request(), $passkey));

        $passkey->setRelation('user', User::factory()->create());

        $this->assertTrue(Passkeys::allowsLogin(request(), $passkey));
    }

    public function test_deactivation_keeps_the_account_and_its_history()
    {
        $user = User::factory()->inactive()->create();

        $this->assertModelExists($user);
    }
}
