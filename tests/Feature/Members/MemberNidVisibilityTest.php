<?php

namespace Tests\Feature\Members;

use App\Enums\Permission;
use App\Models\Activity;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class MemberNidVisibilityTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('uploads');
        Storage::disk('uploads')->put('members/nid/copy.pdf', '%PDF-1.4');

        $this->member = Member::factory()->active()->create([
            'nid_number' => '1234567890',
            'nid_file_path' => 'members/nid/copy.pdf',
        ]);
    }

    /**
     * Every page that shows a member, for a user who may open it.
     *
     * @return list<string>
     */
    private function memberPages(): array
    {
        return [
            route('members.index'),
            route('members.show', $this->member),
            route('members.edit', $this->member),
        ];
    }

    public function test_nid_data_is_absent_from_responses_without_member_view_nid()
    {
        $viewer = User::factory()->withPermissions(Permission::MemberView, Permission::MemberUpdate)->create();

        foreach ($this->memberPages() as $url) {
            $response = $this->actingAs($viewer)->get($url)->assertOk();

            $this->assertStringNotContainsString('1234567890', $response->getContent());
            $this->assertStringNotContainsString('copy.pdf', $response->getContent());
        }

        // Asking for the optional prop does not reveal it either.
        $page = $this->actingAs($viewer)->get(route('members.show', $this->member))->viewData('page');

        $this->assertNull($page['props']['member']['has_nid_file']);

        $this->actingAs($viewer)
            ->get(route('members.show', $this->member), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) $page['version'],
                'X-Inertia-Partial-Component' => 'members/show',
                'X-Inertia-Partial-Data' => 'nid',
            ])
            ->assertOk()
            ->assertJsonMissingPath('props.nid');

        $this->assertSame(0, Activity::query()->where('description', 'nid_viewed')->count());
    }

    public function test_the_nid_download_returns_403_without_member_view_nid()
    {
        $this->actingAs(User::factory()->withPermissions(Permission::MemberView)->create())
            ->get(route('members.nid', $this->member))
            ->assertForbidden();

        // Not even the member themselves, without the permission.
        $this->actingAs($this->member->user)
            ->get(route('members.nid', $this->member))
            ->assertForbidden();
    }

    public function test_the_nid_number_is_revealed_on_request_and_logged()
    {
        $viewer = User::factory()->withPermissions(Permission::MemberView, Permission::MemberViewNid)->create();

        $this->actingAs($viewer)
            ->get(route('members.show', $this->member))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('nid')
                ->where('member.has_nid_file', true));

        $this->assertSame(0, Activity::query()->where('description', 'nid_viewed')->count());

        $this->actingAs($viewer)
            ->get(route('members.show', $this->member))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('nid', fn (Assert $reload) => $reload->where('nid', '1234567890')));

        $activity = Activity::query()->where('description', 'nid_viewed')->sole();
        $this->assertTrue($activity->causer->is($viewer));
        $this->assertTrue($activity->subject->is($this->member));
        $this->assertNotNull($activity->ip_address);
    }

    public function test_the_nid_copy_downloads_with_the_permission_and_is_logged()
    {
        $viewer = User::factory()->withPermissions(Permission::MemberViewNid)->create();

        $response = $this->actingAs($viewer)->get(route('members.nid', $this->member))->assertOk();

        $this->assertSame('%PDF-1.4', $response->streamedContent());
        $this->assertStringContainsString('nid-'.$this->member->id.'.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertTrue(Activity::query()->where('description', 'nid_downloaded')->sole()->causer->is($viewer));
    }

    public function test_the_nid_number_never_reaches_the_audit_log()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('members.update', $this->member), [
            'first_name' => $this->member->first_name,
            'last_name' => $this->member->last_name,
            'name_bn' => $this->member->name_bn,
            'phone' => $this->member->phone,
            'nid_number' => '9876543210',
            'father_name' => $this->member->father_name,
            'father_name_bn' => $this->member->father_name_bn,
            'mother_name' => $this->member->mother_name,
            'mother_name_bn' => $this->member->mother_name_bn,
            'permanent_address' => $this->member->permanent_address,
            'email' => $this->member->user->email,
        ])->assertSessionHasNoErrors();

        $activity = Activity::query()->where('description', 'member_updated')->sole();

        $this->assertTrue($activity->properties['nid_number_changed']);
        $this->assertStringNotContainsString('9876543210', json_encode($activity->getAttributes()) ?: '');
        $this->assertStringNotContainsString('1234567890', json_encode($activity->getAttributes()) ?: '');
    }

    public function test_uploads_are_not_publicly_reachable()
    {
        $this->assertStringStartsWith(storage_path('app/private'), config('filesystems.disks.uploads.root'));
        $this->assertFalse(config('filesystems.disks.uploads.serve'));
    }
}
