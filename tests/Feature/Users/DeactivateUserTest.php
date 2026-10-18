<?php

namespace Tests\Feature\Users;

use App\Enums\Permission;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class DeactivateUserTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    public function test_deactivating_requires_user_deactivate()
    {
        $actor = User::factory()->withPermissions(Permission::UserView, Permission::UserUpdate)->create();
        $user = User::factory()->create();

        $this->actingAs($actor)
            ->post(route('users.deactivate', $user))
            ->assertForbidden();

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_admin_can_deactivate_a_user_and_end_their_sessions()
    {
        config(['session.driver' => 'database']);

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'session-of-user',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->post(route('users.deactivate', $user))
            ->assertRedirect(route('users.edit', $user));

        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-of-user']);

        $changes = Activity::query()->where('description', 'user_deactivated')->sole()->attribute_changes;
        $this->assertTrue($changes['old']['is_active']);
        $this->assertFalse($changes['attributes']['is_active']);
    }

    public function test_users_cannot_deactivate_themselves()
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('users.deactivate', $admin))
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_the_last_active_admin_cannot_be_deactivated()
    {
        $actor = User::factory()->withPermissions(...Permission::cases())->create();
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->inactive()->create();

        $this->actingAs($actor)
            ->post(route('users.deactivate', $admin))
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_a_user_cannot_deactivate_someone_holding_more_permissions()
    {
        $actor = User::factory()->withPermissions(Permission::UserDeactivate)->create();
        $user = User::factory()->withPermissions(Permission::GroceryView)->create();

        $this->actingAs($actor)
            ->post(route('users.deactivate', $user))
            ->assertForbidden();
    }

    public function test_admin_can_reactivate_a_user()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->inactive()->create();

        $this->actingAs($admin)
            ->post(route('users.reactivate', $user))
            ->assertRedirect(route('users.edit', $user));

        $this->assertTrue($user->fresh()->is_active);
        $this->assertDatabaseHas('activity_log', ['description' => 'user_reactivated', 'subject_id' => $user->id]);
    }

    public function test_reactivating_requires_user_deactivate()
    {
        $actor = User::factory()->withPermissions(Permission::UserUpdate)->create();
        $user = User::factory()->inactive()->create();

        $this->actingAs($actor)
            ->post(route('users.reactivate', $user))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->is_active);
    }
}
