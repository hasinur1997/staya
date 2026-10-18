<?php

namespace Tests\Feature\Users;

use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    public function test_user_list_requires_user_view()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('users.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->withPermissions(Permission::UserView)->create())
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/index')
                ->where('can.create', false));
    }

    public function test_create_form_requires_user_create()
    {
        $this->actingAs(User::factory()->withPermissions(Permission::UserView)->create())
            ->get(route('users.create'))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_user_with_roles()
    {
        $admin = User::factory()->admin()->create();
        $member = Role::findByName('Member');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Rahim Uddin',
                'email' => 'rahim@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => [$member->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user = User::where('email', 'rahim@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('Member'));
        $this->assertTrue($user->is_active);

        $activity = Activity::query()->where('description', 'user_created')->sole();
        $this->assertTrue($activity->causer->is($admin));
        $this->assertSame('user', $activity->subject_type);
        $this->assertSame(['Member'], $activity->attribute_changes['attributes']['roles']);
    }

    public function test_storing_a_user_requires_user_create()
    {
        $this->actingAs(User::factory()->withPermissions(Permission::UserView)->create())
            ->post(route('users.store'), [
                'name' => 'Rahim Uddin',
                'email' => 'rahim@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => [],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'rahim@example.com']);
    }

    public function test_a_user_cannot_assign_a_role_holding_permissions_they_lack()
    {
        $actor = User::factory()->withPermissions(Permission::UserCreate, Permission::GroceryView)->create();

        $this->actingAs($actor)
            ->post(route('users.store'), [
                'name' => 'Rahim Uddin',
                'email' => 'rahim@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => [Role::findByName('Manager')->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'rahim@example.com']);
    }

    public function test_a_user_can_assign_a_role_within_their_own_permissions()
    {
        $actor = User::factory()->withPermissions(Permission::UserCreate, Permission::GroceryView)->create();
        $viewer = Role::factory()->withPermissions(Permission::GroceryView)->create();

        $this->actingAs($actor)
            ->post(route('users.store'), [
                'name' => 'Rahim Uddin',
                'email' => 'rahim@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => [$viewer->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'rahim@example.com')->sole()->hasRole($viewer));
    }

    public function test_users_cannot_change_their_own_roles()
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => [Role::findByName('Member')->id],
            ])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->hasRole('Admin'));
    }

    public function test_users_can_edit_their_own_details_without_touching_roles()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('users.update', $admin), [
                'name' => 'New name',
                'email' => $admin->email,
                'roles' => [Role::admin()->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('New name', $admin->fresh()->name);
    }

    public function test_a_user_cannot_edit_someone_holding_more_permissions()
    {
        $actor = User::factory()->withPermissions(Permission::UserView, Permission::UserUpdate)->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($actor)->get(route('users.edit', $admin))->assertForbidden();

        $this->actingAs($actor)
            ->put(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => 'taken-over@example.com',
            ])
            ->assertForbidden();

        $this->assertNotSame('taken-over@example.com', $admin->fresh()->email);
    }

    public function test_updating_a_user_logs_before_and_after_values()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Old name']);

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'name' => 'New name',
                'email' => $user->email,
                'roles' => [Role::findByName('Member')->id],
            ])
            ->assertSessionHasNoErrors();

        $changes = Activity::query()->where('description', 'user_updated')->sole()->attribute_changes;

        $this->assertSame('Old name', $changes['old']['name']);
        $this->assertSame([], $changes['old']['roles']);
        $this->assertSame('New name', $changes['attributes']['name']);
        $this->assertSame(['Member'], $changes['attributes']['roles']);
    }

    public function test_the_admin_role_cannot_be_removed_from_the_last_active_admin()
    {
        $actor = User::factory()->withPermissions(...Permission::cases())->create();
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->inactive()->create();

        $this->actingAs($actor)
            ->put(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => [],
            ])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->hasRole('Admin'));
    }

    public function test_the_admin_role_can_be_removed_while_another_active_admin_remains()
    {
        $actor = User::factory()->admin()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($actor)
            ->put(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($admin->fresh()->hasRole('Admin'));
    }

    public function test_a_denied_inertia_submit_returns_with_the_reason()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('users.edit', $admin))
            ->withHeader('X-Inertia', 'true')
            ->put(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => [],
            ])
            ->assertRedirect(route('users.edit', $admin));

        $this->assertTrue($admin->fresh()->hasRole('Admin'));
    }
}
