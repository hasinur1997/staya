<?php

namespace Tests\Feature\Members;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class InactiveMemberAccessTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    public function test_an_inactive_member_can_sign_in_and_read()
    {
        $member = Member::factory()->inactive()->create();

        $this->post(route('login.store'), [
            'email' => $member->user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($member->user);

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('members.show', $member))->assertOk();
        $this->get(route('grocery.index'))->assertOk();
    }

    public function test_an_inactive_member_is_not_an_active_member()
    {
        $inactive = Member::factory()->inactive()->create();
        $active = Member::factory()->active()->create();

        $this->assertFalse($inactive->user->isActiveMember());
        $this->assertTrue($active->user->isActiveMember());
    }
}
