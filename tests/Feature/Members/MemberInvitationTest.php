<?php

namespace Tests\Feature\Members;

use App\Concerns\BusinessCalendar;
use App\Enums\MemberStatus;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Mail\MemberInvitationMail;
use App\Models\Activity;
use App\Models\Member;
use App\Models\MemberInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMemberForms;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class MemberInvitationTest extends TestCase
{
    use BuildsMemberForms, RefreshDatabase, SeedsAccessControl;

    private const string TOKEN = 'plain-test-token-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('uploads');
        $this->travelTo(BusinessCalendar::today()->setDate(2026, 10, 18)->setTime(12, 0));
    }

    private function staff(): User
    {
        return User::factory()->withPermissions(...[...SystemRole::Member->defaultPermissions(), Permission::MemberCreate])->create();
    }

    private function invitation(string $state = 'pending'): MemberInvitation
    {
        $factory = MemberInvitation::factory()->withToken(self::TOKEN)->state(['email' => 'new.person@example.com']);

        $factory = match ($state) {
            'expired' => $factory->expired(),
            'used' => $factory->used(),
            'revoked' => $factory->revoked(),
            default => $factory,
        };

        return $factory->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function accepted(array $overrides = []): array
    {
        return $this->memberForm(['password' => 'password', 'password_confirmation' => 'password', ...$overrides]);
    }

    public function test_managing_invitations_requires_member_create()
    {
        $invitation = $this->invitation();
        $viewer = User::factory()->withPermissions(Permission::MemberView)->create();

        $this->actingAs($viewer)->get(route('invitations.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('invitations.store'), ['email' => 'a@example.com'])->assertForbidden();
        $this->actingAs($viewer)->post(route('invitations.resend', $invitation))->assertForbidden();
        $this->actingAs($viewer)->post(route('invitations.revoke', $invitation))->assertForbidden();

        $this->assertNull($invitation->fresh()->revoked_at);
        $this->assertSame(1, MemberInvitation::query()->count());
    }

    public function test_guests_are_sent_to_login_for_the_staff_screens()
    {
        $this->get(route('invitations.index'))->assertRedirect(route('login'));
        $this->post(route('invitations.store'), ['email' => 'a@example.com'])->assertRedirect(route('login'));
    }

    public function test_sending_an_invitation_emails_a_link_and_stores_only_the_token_hash()
    {
        Mail::fake();

        $this->actingAs($this->staff())
            ->post(route('invitations.store'), ['email' => 'Rahim@Example.com'])
            ->assertSessionHasNoErrors();

        $invitation = MemberInvitation::query()->sole();
        $this->assertSame('rahim@example.com', $invitation->email);
        $this->assertTrue($invitation->expires_at->equalTo(now()->addDays(7)));

        Mail::assertSent(MemberInvitationMail::class, function (MemberInvitationMail $mail) use ($invitation) {
            $token = basename($mail->url);

            return $mail->hasTo('rahim@example.com')
                && str_starts_with($mail->url, url('/join').'/')
                && MemberInvitation::hashToken($token) === $invitation->token_hash
                && $token !== $invitation->token_hash;
        });

        $activity = Activity::query()->where('description', 'invitation_sent')->sole();
        $this->assertSame('rahim@example.com', $activity->attribute_changes['attributes']['email']);
        $this->assertStringNotContainsString($invitation->token_hash, (string) json_encode($activity->attribute_changes));
    }

    public function test_the_link_lifetime_can_be_chosen_between_one_and_thirty_days()
    {
        Mail::fake();
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('invitations.store'), ['email' => 'a@example.com', 'expires_in_days' => 3]);
        $this->assertTrue(MemberInvitation::query()->where('email', 'a@example.com')->sole()->expires_at->equalTo(now()->addDays(3)));

        $this->actingAs($staff)->post(route('invitations.store'), ['email' => 'b@example.com', 'expires_in_days' => 0])->assertSessionHasErrors('expires_in_days');
        $this->actingAs($staff)->post(route('invitations.store'), ['email' => 'b@example.com', 'expires_in_days' => 31])->assertSessionHasErrors('expires_in_days');
        $this->assertSame(1, MemberInvitation::query()->count());
    }

    public function test_an_email_that_already_has_an_account_cannot_be_invited()
    {
        Mail::fake();
        $existing = User::factory()->create();

        $this->actingAs($this->staff())->post(route('invitations.store'), ['email' => $existing->email])->assertSessionHasErrors('email');

        $this->assertSame(0, MemberInvitation::query()->count());
        Mail::assertNothingSent();
    }

    public function test_inviting_the_same_address_again_cancels_the_older_pending_link()
    {
        Mail::fake();
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('invitations.store'), ['email' => 'a@example.com']);
        $this->actingAs($staff)->post(route('invitations.store'), ['email' => 'a@example.com']);

        $invitations = MemberInvitation::query()->orderBy('id')->get();
        $this->assertCount(2, $invitations);
        $this->assertNotNull($invitations[0]->revoked_at);
        $this->assertNull($invitations[1]->revoked_at);
        $this->assertSame(1, Activity::query()->where('description', 'invitation_revoked')->count());
    }

    public function test_the_list_shows_each_invitation_with_its_status_word()
    {
        $this->invitation('pending');
        MemberInvitation::factory()->expired()->create();
        MemberInvitation::factory()->used()->create();
        MemberInvitation::factory()->revoked()->create();

        $this->actingAs($this->staff())->get(route('invitations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('members/invitations/index')
                ->has('invitations.data', 4)
                ->where('invitations.data', fn ($rows) => collect($rows)->pluck('status')->sort()->values()->all() === ['expired', 'pending', 'revoked', 'used']
                    && collect($rows)->every(fn ($row) => ! array_key_exists('token_hash', $row))));
    }

    public function test_a_valid_link_shows_the_form_for_the_invited_email()
    {
        $this->invitation();

        $this->get(route('invitations.show', self::TOKEN))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/invitation')
                ->where('state', 'pending')
                ->where('email', 'new.person@example.com')
                ->whereType('expiresAt', 'string'));
    }

    public function test_a_used_link_says_it_was_already_used_and_reveals_nothing()
    {
        $this->invitation('used');

        $this->get(route('invitations.show', self::TOKEN))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('state', 'used')->where('email', null)->where('expiresAt', null));
    }

    public function test_expired_cancelled_and_unknown_links_show_their_own_state()
    {
        $this->invitation('expired');
        $this->get(route('invitations.show', self::TOKEN))->assertInertia(fn (Assert $page) => $page->where('state', 'expired')->where('email', null));

        MemberInvitation::query()->delete();
        $this->invitation('revoked');
        $this->get(route('invitations.show', self::TOKEN))->assertInertia(fn (Assert $page) => $page->where('state', 'revoked'));

        $this->get(route('invitations.show', 'no-such-token'))->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));
    }

    public function test_a_link_stops_working_once_its_expiry_passes()
    {
        $invitation = MemberInvitation::factory()->withToken(self::TOKEN)->create(['expires_at' => now()->addDay()]);

        $this->get(route('invitations.show', self::TOKEN))->assertInertia(fn (Assert $page) => $page->where('state', 'pending'));

        $this->travel(2)->days();

        $this->get(route('invitations.show', self::TOKEN))->assertInertia(fn (Assert $page) => $page->where('state', 'expired'));
        $this->post(route('invitations.accept', self::TOKEN), $this->accepted())->assertRedirect(route('invitations.show', self::TOKEN));
        $this->assertSame(0, Member::query()->count());
        $this->assertNull($invitation->fresh()->used_at);
    }

    public function test_accepting_creates_an_approved_member_with_a_verified_account_and_signs_them_in()
    {
        Notification::fake();
        $inviter = $this->staff();
        $invitation = MemberInvitation::factory()->withToken(self::TOKEN)->create(['email' => 'new.person@example.com', 'invited_by' => $inviter->id]);

        $this->post(route('invitations.accept', self::TOKEN), $this->accepted(['email' => 'attacker@example.com']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'new.person@example.com')->sole();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull(User::query()->where('email', 'attacker@example.com')->first());
        $this->assertTrue($user->hasRole(SystemRole::Member->value));
        $this->assertAuthenticatedAs($user);

        $member = Member::query()->sole();
        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertSame($inviter->id, $member->reviewed_by);
        $this->assertSame('+8801712345678', $member->phone);
        $this->assertSame(BusinessCalendar::today()->toDateString(), $member->currentStay()->sole()->joined_on->toDateString());
        Storage::disk('uploads')->assertExists($member->nid_file_path);

        $invitation->refresh();
        $this->assertNotNull($invitation->used_at);
        $this->assertSame($member->id, $invitation->member_id);

        $accepted = Activity::query()->where('description', 'invitation_accepted')->sole();
        $this->assertSame($user->id, $accepted->causer_id);
        Notification::assertNothingSent();
    }

    public function test_a_link_cannot_be_used_twice()
    {
        $this->invitation();

        $this->post(route('invitations.accept', self::TOKEN), $this->accepted())->assertRedirect(route('dashboard'));
        auth()->logout();

        $this->post(route('invitations.accept', self::TOKEN), $this->accepted(['nid_number' => '9876543210', 'password' => 'other-password', 'password_confirmation' => 'other-password']))
            ->assertRedirect(route('invitations.show', self::TOKEN));

        $this->assertSame(1, Member::query()->count());
        $this->assertSame(1, User::query()->where('email', 'new.person@example.com')->count());
        $this->assertGuest();

        $this->get(route('invitations.show', self::TOKEN))->assertInertia(fn (Assert $page) => $page->where('state', 'used'));
    }

    public function test_cancelled_and_expired_links_cannot_be_accepted()
    {
        foreach (['revoked', 'expired'] as $state) {
            MemberInvitation::query()->delete();
            $this->invitation($state);

            $this->post(route('invitations.accept', self::TOKEN), $this->accepted())->assertRedirect(route('invitations.show', self::TOKEN));
        }

        $this->assertSame(0, Member::query()->count());
        $this->assertSame(0, User::query()->where('email', 'new.person@example.com')->count());
    }

    public function test_an_incomplete_form_keeps_the_invitation_pending()
    {
        $invitation = $this->invitation();

        $this->post(route('invitations.accept', self::TOKEN), $this->accepted(['first_name' => '', 'nid_file' => null, 'password_confirmation' => 'different']))
            ->assertSessionHasErrors(['first_name', 'nid_file', 'password']);

        $this->assertNull($invitation->fresh()->used_at);
        $this->assertSame(0, Member::query()->count());
    }

    public function test_an_nid_already_registered_is_rejected_and_the_invitation_stays_usable()
    {
        Member::factory()->active()->create(['nid_number' => '1234567890']);
        $invitation = $this->invitation();

        $this->post(route('invitations.accept', self::TOKEN), $this->accepted(['nid_number' => '1234 5678 90']))->assertSessionHasErrors('nid_number');

        $this->assertNull($invitation->fresh()->used_at);
    }

    public function test_an_account_created_since_the_invite_blocks_acceptance()
    {
        $invitation = $this->invitation();
        User::factory()->create(['email' => 'new.person@example.com']);

        $this->post(route('invitations.accept', self::TOKEN), $this->accepted())->assertSessionHasErrors('email');

        $this->assertNull($invitation->fresh()->used_at);
        $this->assertSame(0, Member::query()->count());
    }

    public function test_resending_sends_a_new_link_and_the_old_one_stops_working()
    {
        Mail::fake();
        $old = $this->invitation();

        $this->actingAs($this->staff())->post(route('invitations.resend', $old))->assertSessionHasNoErrors();

        $this->assertNotNull($old->fresh()->revoked_at);
        $this->assertSame(2, MemberInvitation::query()->count());
        Mail::assertSent(MemberInvitationMail::class, fn (MemberInvitationMail $mail) => $mail->hasTo('new.person@example.com'));

        $this->get(route('invitations.show', self::TOKEN))->assertInertia(fn (Assert $page) => $page->where('state', 'revoked'));
    }

    public function test_an_accepted_invitation_cannot_be_resent_or_cancelled()
    {
        Mail::fake();
        $used = $this->invitation('used');
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('invitations.resend', $used))->assertSessionHasErrors('invitation');
        $this->actingAs($staff)->post(route('invitations.revoke', $used))->assertSessionHasErrors('invitation');

        $this->assertNull($used->fresh()->revoked_at);
        Mail::assertNothingSent();
    }

    public function test_cancelling_a_pending_invitation_kills_the_link_and_is_logged()
    {
        $invitation = $this->invitation();

        $this->actingAs($this->staff())->post(route('invitations.revoke', $invitation))->assertSessionHasNoErrors();

        $this->assertNotNull($invitation->fresh()->revoked_at);
        $this->get(route('invitations.show', self::TOKEN))->assertInertia(fn (Assert $page) => $page->where('state', 'revoked'));
        $this->assertSame(1, Activity::query()->where('description', 'invitation_revoked')->count());
    }

    public function test_invitations_have_no_edit_or_delete_routes()
    {
        $invitation = $this->invitation();
        $staff = $this->staff();
        $path = str_replace('/revoke', '', route('invitations.revoke', $invitation, false));

        $this->assertGreaterThanOrEqual(400, $this->actingAs($staff)->delete($path)->getStatusCode());
        $this->assertGreaterThanOrEqual(400, $this->actingAs($staff)->put($path)->getStatusCode());
        $this->assertSame(1, MemberInvitation::query()->count());
    }
}
