<?php

namespace Tests\Feature\Members;

use App\Enums\Permission;
use App\Models\Activity;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\User;
use App\Policies\MemberPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class DeleteMemberTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('uploads');
    }

    public function test_a_member_without_financial_records_is_deleted_with_their_account_and_files()
    {
        $admin = User::factory()->admin()->create();
        Storage::disk('uploads')->put('members/nid/a.pdf', 'pdf');
        $member = Member::factory()->rejected()->create(['nid_file_path' => 'members/nid/a.pdf', 'nid_number' => '1234567890']);
        $userId = $member->user_id;

        $this->actingAs($admin)
            ->delete(route('members.destroy', $member))
            ->assertRedirect(route('members.index'));

        $this->assertModelMissing($member);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertDatabaseCount('member_stays', 0);
        Storage::disk('uploads')->assertMissing('members/nid/a.pdf');

        $activity = Activity::query()->where('description', 'member_deleted')->sole();
        $this->assertSame('deleted', $activity->properties['account']);
        $this->assertSame('rejected', $activity->attribute_changes['old']['status']);

        // The NID number is free to register again.
        $this->assertFalse(Member::query()->where('nid_number_hash', Member::nidHash('1234567890'))->exists());
    }

    public function test_an_account_that_other_records_point_at_is_deactivated_instead()
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create();
        LedgerEntry::factory()->create(['created_by' => $member->user_id]);

        $this->actingAs($admin)->delete(route('members.destroy', $member))->assertRedirect();

        $this->assertModelMissing($member);
        $this->assertFalse($member->user->fresh()->is_active);
    }

    public function test_a_staff_account_is_kept_when_its_member_profile_is_deleted()
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create();
        $member->user->assignRole('Manager');

        $this->actingAs($admin)->delete(route('members.destroy', $member))->assertRedirect();

        $user = $member->user->fresh();
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('Manager'));
    }

    public function test_deleting_requires_member_delete()
    {
        $member = Member::factory()->active()->create();

        $this->actingAs(User::factory()->withPermissions(Permission::MemberView, Permission::MemberUpdate)->create())
            ->delete(route('members.destroy', $member))
            ->assertForbidden();

        $this->assertModelExists($member);
    }

    public function test_nobody_deletes_their_own_member_profile()
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['user_id' => $admin->id]);

        $this->actingAs($admin)->delete(route('members.destroy', $member))->assertForbidden();

        $this->assertModelExists($member);
    }

    public function test_hard_delete_is_refused_when_the_member_has_financial_records()
    {
        $admin = User::factory()->admin()->create();
        $member = $this->partialMock(Member::class, fn ($mock) => $mock->shouldReceive('hasFinancialRecords')->andReturn(true));

        $response = (new MemberPolicy)->delete($admin, $member);

        $this->assertTrue($response->denied());
        $this->assertSame('This member has financial records, so they can only be deactivated.', $response->message());
    }
}
