<?php

namespace Tests\Feature\Members;

use App\Actions\Members\CreateMember;
use App\Concerns\BusinessCalendar;
use App\Enums\MemberStatus;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Activity;
use App\Models\Member;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMemberForms;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use BuildsMemberForms, RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('uploads');
        $this->travelTo(BusinessCalendar::today()->setDate(2026, 10, 18)->setTime(12, 0));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function newMember(array $overrides = []): array
    {
        return $this->memberForm([
            'account' => 'new',
            'email' => 'rahim@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'joined_on' => '2026-10-01',
            ...$overrides,
        ]);
    }

    public function test_member_list_requires_member_view()
    {
        Member::factory()->active()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('members.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->withPermissions(Permission::MemberView)->create())
            ->get(route('members.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('members/index')
                ->has('members', 1)
                ->where('can.create', false));
    }

    public function test_admin_creates_a_member_and_their_account_in_one_step()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('members.store'), $this->newMember())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $member = Member::query()->sole();
        $user = $member->user;

        $this->assertSame('rahim@example.com', $user->email);
        $this->assertSame('Rahim Uddin', $user->name);
        $this->assertTrue($user->hasRole('Member'));
        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertTrue($member->reviewer->is($admin));
        $this->assertSame('2026-10-01', $member->currentStay->joined_on->toDateString());
        Storage::disk('uploads')->assertExists($member->nid_file_path);
        Notification::assertSentTo($user, VerifyEmail::class);

        $activity = Activity::query()->where('description', 'member_created')->sole();
        $this->assertTrue($activity->causer->is($admin));
        $this->assertSame('rahim@example.com', $activity->attribute_changes['attributes']['email']);
        $this->assertSame([['joined_on' => '2026-10-01', 'left_on' => null]], $activity->attribute_changes['attributes']['stays']);
    }

    public function test_bangla_and_english_names_are_required_for_the_member_and_parents()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('members.store'), $this->newMember(['name_bn' => '', 'father_name_bn' => '', 'mother_name_bn' => '']))
            ->assertSessionHasErrors(['name_bn', 'father_name_bn', 'mother_name_bn']);

        $this->actingAs($admin)
            ->post(route('members.store'), $this->newMember(['first_name' => '', 'father_name' => '', 'mother_name' => '']))
            ->assertSessionHasErrors(['first_name', 'father_name', 'mother_name']);

        $this->assertSame(0, Member::query()->count());
    }

    public function test_the_name_follows_the_language_on_screen()
    {
        $member = Member::factory()->create(['first_name' => 'Rahim', 'last_name' => 'Uddin', 'name_bn' => 'রহিম উদ্দিন']);

        app()->setLocale('en');
        $this->assertSame('Rahim Uddin', $member->full_name);

        app()->setLocale('bn');
        $this->assertSame('রহিম উদ্দিন', $member->full_name);
        $this->assertSame('Rahim Uddin', $member->english_name);
    }

    public function test_creating_a_member_requires_member_create()
    {
        $this->actingAs(User::factory()->withPermissions(Permission::MemberView)->create())
            ->post(route('members.store'), $this->newMember())
            ->assertForbidden();

        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseMissing('users', ['email' => 'rahim@example.com']);
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_a_creator_must_hold_the_member_roles_permissions()
    {
        $this->actingAs(User::factory()->withPermissions(Permission::MemberCreate)->create())
            ->post(route('members.store'), $this->newMember())
            ->assertForbidden();

        $this->assertDatabaseCount('members', 0);
    }

    public function test_an_existing_account_can_be_linked()
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['name' => 'Old name']);

        $this->actingAs($admin)
            ->post(route('members.store'), $this->newMember(['account' => 'existing', 'user_id' => $manager->id]))
            ->assertSessionHasNoErrors();

        $manager->refresh();
        $this->assertTrue($manager->member->is(Member::query()->sole()));
        $this->assertSame('Rahim Uddin', $manager->name);
        $this->assertTrue($manager->hasRole('Member'));
    }

    public function test_an_admin_can_add_themselves_as_a_member_without_changing_their_roles()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('members.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('linkableUsers', 1)
                ->where('linkableUsers.0.id', $admin->id)
                ->where('linkableUsers.0.is_self', true));

        $this->actingAs($admin)
            ->post(route('members.store'), $this->newMember(['account' => 'existing', 'user_id' => $admin->id]))
            ->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertTrue($admin->member->is(Member::query()->sole()));
        $this->assertSame(MemberStatus::Active, $admin->member->status);
        $this->assertTrue($admin->hasRole('Admin'));
        $this->assertFalse($admin->hasRole('Member'));
        $this->assertSame($admin->id, $admin->member->reviewed_by);
        $this->assertTrue($admin->isActiveMember());
    }

    public function test_adding_yourself_as_a_member_still_needs_member_create()
    {
        $user = User::factory()->withPermissions(...SystemRole::Member->defaultPermissions())->create();

        $this->actingAs($user)
            ->post(route('members.store'), $this->newMember(['account' => 'existing', 'user_id' => $user->id]))
            ->assertForbidden();

        $this->assertDatabaseCount('members', 0);
    }

    public function test_an_account_with_a_member_profile_cannot_be_linked_again()
    {
        $admin = User::factory()->admin()->create();
        $existing = Member::factory()->active()->create();

        $this->actingAs($admin)
            ->post(route('members.store'), $this->newMember(['account' => 'existing', 'user_id' => $existing->user_id]))
            ->assertSessionHasErrors(['user_id' => 'This user already has a member profile.']);
    }

    public function test_linking_an_account_needs_the_right_to_edit_it()
    {
        $actor = User::factory()->withPermissions(...[...SystemRole::Member->defaultPermissions(), Permission::MemberCreate])->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($actor)
            ->post(route('members.store'), $this->newMember(['account' => 'existing', 'user_id' => $admin->id]))
            ->assertForbidden();

        $this->assertDatabaseCount('members', 0);
    }

    public function test_the_join_date_cannot_be_in_the_future()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('members.store'), $this->newMember(['joined_on' => '2026-10-19']))
            ->assertSessionHasErrors(['joined_on' => 'The date cannot be in the future.']);
    }

    public function test_a_failed_save_leaves_no_files_behind()
    {
        $admin = User::factory()->admin()->create();
        Member::factory()->create(['nid_number' => '9999999999']);

        // Passes validation, then the database refuses the duplicate email.
        User::factory()->create(['email' => 'taken@example.com']);

        try {
            app(CreateMember::class)->handle(
                $admin,
                ['first_name' => 'A', 'last_name' => 'B', 'phone' => '+8801712345678', 'nid_number' => '1111111111', 'father_name' => 'F', 'mother_name' => 'M', 'permanent_address' => 'X'],
                ['email' => 'taken@example.com', 'password' => 'password'],
                '2026-10-01',
                UploadedFile::fake()->create('nid.pdf', 10, 'application/pdf'),
            );
            $this->fail('The duplicate email should have been refused.');
        } catch (QueryException) {
            $this->assertSame([], Storage::disk('uploads')->allFiles());
        }
    }

    public function test_updating_syncs_the_account_and_logs_before_and_after()
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['first_name' => 'Old']);

        $this->actingAs($admin)
            ->put(route('members.update', $member), [
                ...$this->memberForm(['nid_file' => null, 'nid_number' => $member->nid_number]),
                'email' => 'new@example.com',
            ])
            ->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertSame('Rahim', $member->first_name);
        $this->assertSame('Rahim Uddin', $member->user->name);
        $this->assertSame('new@example.com', $member->user->email);
        $this->assertNull($member->user->email_verified_at);

        $activity = Activity::query()->where('description', 'member_updated')->sole();
        $this->assertSame('Old', $activity->attribute_changes['old']['first_name']);
        $this->assertSame('Rahim', $activity->attribute_changes['attributes']['first_name']);
        $this->assertFalse($activity->properties['nid_number_changed']);
    }

    public function test_updating_requires_member_update()
    {
        $member = Member::factory()->active()->create();

        $this->actingAs(User::factory()->withPermissions(Permission::MemberView)->create())
            ->put(route('members.update', $member), [...$this->memberForm(), 'email' => $member->user->email])
            ->assertForbidden();
    }

    public function test_changing_the_email_of_someone_who_outranks_you_is_refused()
    {
        $actor = User::factory()->withPermissions(Permission::MemberView, Permission::MemberUpdate, Permission::UserUpdate)->create();
        $member = Member::factory()->active()->create();
        $member->user->assignRole('Admin');

        $this->actingAs($actor)
            ->put(route('members.update', $member), [
                ...$this->memberForm(['nid_file' => null]),
                'email' => 'taken-over@example.com',
            ])
            ->assertForbidden();

        $this->assertNotSame('taken-over@example.com', $member->user->fresh()->email);
    }

    public function test_nid_fields_cannot_be_changed_without_member_view_nid()
    {
        $actor = User::factory()->withPermissions(Permission::MemberView, Permission::MemberUpdate)->create();
        $member = Member::factory()->active()->create(['nid_number' => '1111111111']);
        $form = $this->memberForm(['nid_file' => null]);

        $this->actingAs($actor)
            ->put(route('members.update', $member), [...$form, 'email' => $member->user->email])
            ->assertSessionHasErrors('nid_number');

        unset($form['nid_number']);

        $this->actingAs($actor)
            ->put(route('members.update', $member), [...$form, 'email' => $member->user->email])
            ->assertSessionHasNoErrors();

        $this->assertSame('1111111111', $member->fresh()->nid_number);
    }

    public function test_a_replaced_nid_copy_deletes_the_old_file()
    {
        $admin = User::factory()->admin()->create();
        Storage::disk('uploads')->put('members/nid/old.pdf', 'old');
        $member = Member::factory()->active()->create(['nid_file_path' => 'members/nid/old.pdf']);

        $this->actingAs($admin)
            ->put(route('members.update', $member), [...$this->memberForm(), 'email' => $member->user->email])
            ->assertSessionHasNoErrors();

        Storage::disk('uploads')->assertMissing('members/nid/old.pdf');
        Storage::disk('uploads')->assertExists($member->fresh()->nid_file_path);
        $this->assertTrue(Activity::query()->where('description', 'member_updated')->sole()->properties['nid_file_replaced']);
    }

    public function test_members_can_view_their_own_profile_only()
    {
        $member = Member::factory()->active()->create();
        $other = Member::factory()->active()->create();

        $this->actingAs($member->user)->get(route('members.show', $member))->assertOk();
        $this->actingAs($member->user)->get(route('members.show', $other))->assertForbidden();
        $this->actingAs($member->user)->get(route('members.edit', $member))->assertForbidden();
    }

    public function test_photos_are_served_only_to_those_who_may_view_the_member()
    {
        Storage::disk('uploads')->put('members/photos/a.jpg', 'jpeg');
        $member = Member::factory()->active()->create(['photo_path' => 'members/photos/a.jpg']);

        $this->actingAs(Member::factory()->active()->create()->user)
            ->get(route('members.photo', $member))
            ->assertForbidden();

        $this->actingAs($member->user)->get(route('members.photo', $member))->assertOk();
    }
}
