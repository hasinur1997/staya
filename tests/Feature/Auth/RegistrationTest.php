<?php

namespace Tests\Feature\Auth;

use App\Enums\MemberStatus;
use App\Models\Activity;
use App\Models\Member;
use App\Models\MessSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;
use Tests\Concerns\BuildsMemberForms;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use BuildsMemberForms, RefreshDatabase, SeedsAccessControl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());

        Storage::fake('uploads');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function signUp(array $overrides = []): array
    {
        return $this->memberForm([
            'email' => 'rahim@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$overrides,
        ]);
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_a_sign_up_creates_a_pending_member_with_no_roles()
    {
        $this->post(route('register.store'), $this->signUp([
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ]))->assertSessionHasNoErrors();

        $this->assertAuthenticated();

        $user = User::where('email', 'rahim@example.com')->sole();
        $member = $user->member;

        $this->assertSame('Rahim Uddin', $user->name);
        $this->assertCount(0, $user->roles);
        $this->assertSame(MemberStatus::Pending, $member->status);
        $this->assertSame('+8801712345678', $member->phone);
        $this->assertSame('1234567890', $member->nid_number);
        $this->assertCount(0, $member->stays);

        Storage::disk('uploads')->assertExists([$member->nid_file_path, $member->photo_path]);
        $this->assertStringStartsWith('members/nid/', $member->nid_file_path);
        $this->assertStringEndsNotWith('nid.pdf', $member->nid_file_path);

        $this->assertTrue(Activity::query()->where('description', 'registration_submitted')->sole()->causer->is($user));
    }

    public function test_the_nid_number_is_encrypted_at_rest()
    {
        $this->post(route('register.store'), $this->signUp())->assertSessionHasNoErrors();

        $stored = DB::table('members')->value('nid_number');

        $this->assertNotSame('1234567890', $stored);
        $this->assertStringNotContainsString('1234567890', (string) $stored);
        $this->assertSame(Member::nidHash('1234567890'), DB::table('members')->value('nid_number_hash'));
    }

    public function test_a_new_applicant_is_sent_to_the_status_page()
    {
        $this->post(route('register.store'), $this->signUp())
            ->assertRedirect(route('dashboard', absolute: false));

        $this->get(route('dashboard'))->assertRedirect(route('registration.status'));
    }

    public function test_sign_up_validates_the_member_fields()
    {
        $this->post(route('register.store'), $this->signUp([
            'first_name' => '',
            'phone' => '0171234567',
            'nid_number' => '12345678901',
            'email' => 'not-an-email',
        ]))->assertSessionHasErrors(['first_name', 'phone', 'nid_number', 'email']);

        $this->assertGuest();
        $this->assertDatabaseCount('members', 0);
    }

    public function test_the_nid_copy_is_required_and_checked_by_its_content()
    {
        $this->post(route('register.store'), $this->signUp(['nid_file' => null]))
            ->assertSessionHasErrors('nid_file');

        // Named like a PDF, but its content is an executable.
        $this->post(route('register.store'), $this->signUp([
            'nid_file' => UploadedFile::fake()->create('nid.pdf', 100, 'application/x-msdownload'),
        ]))->assertSessionHasErrors('nid_file');

        $this->assertDatabaseCount('members', 0);
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_uploads_are_limited_to_the_size_in_settings()
    {
        MessSetting::current()->update(['max_upload_kb' => 100]);

        $this->post(route('register.store'), $this->signUp([
            'nid_file' => UploadedFile::fake()->create('nid.pdf', 101, 'application/pdf'),
        ]))->assertSessionHasErrors('nid_file');

        $this->post(route('register.store'), $this->signUp([
            'nid_file' => UploadedFile::fake()->create('nid.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasNoErrors();
    }

    public function test_photos_must_be_raster_images()
    {
        $this->post(route('register.store'), $this->signUp([
            'photo' => UploadedFile::fake()->create('me.svg', 10, 'image/svg+xml'),
        ]))->assertSessionHasErrors('photo');
    }

    public function test_an_nid_number_can_only_be_registered_once()
    {
        Member::factory()->create(['nid_number' => '1234567890']);

        $this->post(route('register.store'), $this->signUp())
            ->assertSessionHasErrors(['nid_number' => 'This NID number is already registered.']);
    }

    public function test_seventeen_digit_nid_numbers_are_accepted()
    {
        $this->post(route('register.store'), $this->signUp(['nid_number' => '19901234567890123']))
            ->assertSessionHasNoErrors();
    }
}
