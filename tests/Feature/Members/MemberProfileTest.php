<?php

namespace Tests\Feature\Members;

use App\Concerns\BusinessCalendar;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\BuyerPayable;
use App\Models\Deposit;
use App\Models\GroceryPurchase;
use App\Models\Meal;
use App\Models\MealType;
use App\Models\Member;
use App\Models\MemberStay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SeedsAccessControl;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase, SeedsAccessControl;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('uploads');
        $this->travelTo(BusinessCalendar::today()->setDate(2026, 10, 18)->setTime(12, 0));

        MealType::query()->delete();
        $this->member = Member::factory()->active(withStay: false)->create();
        MemberStay::factory()->for($this->member)->between('2026-08-01')->create();
    }

    private function editor(Permission ...$extra): User
    {
        return User::factory()->withPermissions(Permission::MemberView, Permission::MemberUpdate, ...$extra)->create();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function patchSection(User $actor, string $section, array $data)
    {
        return $this->actingAs($actor)->patch(route('members.sections.update', [$this->member, $section]), $data);
    }

    public function test_the_personal_card_updates_the_name_and_phone_and_the_account_name()
    {
        $editor = $this->editor();

        $this->patchSection($editor, 'personal', [
            'first_name' => 'Karim',
            'last_name' => 'Hossain',
            'name_bn' => 'করিম হোসেন',
            'phone' => '01812-345678',
            'email' => $this->member->user->email,
        ])->assertRedirect();

        $this->member->refresh();
        $this->assertSame('Karim', $this->member->first_name);
        $this->assertSame('+8801812345678', $this->member->phone);
        $this->assertSame('Karim Hossain', $this->member->user->name);
    }

    public function test_the_family_card_changes_only_its_own_fields()
    {
        $phone = $this->member->phone;

        $this->patchSection($this->editor(), 'family', [
            'father_name' => 'New Father',
            'father_name_bn' => 'নতুন পিতা',
            'mother_name' => 'New Mother',
            'mother_name_bn' => 'নতুন মাতা',
            'permanent_address' => 'New address',
            'phone' => '01999999999',
        ])->assertRedirect();

        $this->member->refresh();
        $this->assertSame('New Father', $this->member->father_name);
        $this->assertSame('New address', $this->member->permanent_address);
        $this->assertSame($phone, $this->member->phone);
    }

    public function test_a_card_cannot_be_saved_without_member_update()
    {
        $this->patchSection(User::factory()->withPermissions(Permission::MemberView)->create(), 'family', [
            'father_name' => 'X', 'father_name_bn' => 'এক্স', 'mother_name' => 'Y', 'mother_name_bn' => 'ওয়াই', 'permanent_address' => 'Z',
        ])->assertForbidden();

        // A member cannot edit their own profile either.
        $this->patchSection($this->member->user, 'family', [
            'father_name' => 'X', 'father_name_bn' => 'এক্স', 'mother_name' => 'Y', 'mother_name_bn' => 'ওয়াই', 'permanent_address' => 'Z',
        ])->assertForbidden();

        $this->assertNotSame('X', $this->member->refresh()->father_name);
    }

    public function test_changing_the_email_needs_the_right_to_edit_the_account()
    {
        $this->patchSection($this->editor(), 'personal', [
            'first_name' => 'A', 'last_name' => 'B', 'name_bn' => 'এ বি', 'phone' => '01712345678', 'email' => 'new@example.com',
        ])->assertForbidden();

        $this->patchSection(User::factory()->admin()->create(), 'personal', [
            'first_name' => 'A', 'last_name' => 'B', 'name_bn' => 'এ বি', 'phone' => '01712345678', 'email' => 'new@example.com',
        ])->assertRedirect();

        $this->assertSame('new@example.com', $this->member->user->refresh()->email);
    }

    public function test_the_nid_card_needs_the_right_to_see_nids()
    {
        $this->patchSection($this->editor(), 'nid', ['nid_number' => '1234567890'])->assertForbidden();

        $this->patchSection($this->editor(Permission::MemberViewNid), 'nid', ['nid_number' => '1234567890'])->assertRedirect();

        $this->assertSame('1234567890', $this->member->refresh()->nid_number);
    }

    public function test_invalid_input_is_rejected_per_section()
    {
        $this->patchSection($this->editor(), 'personal', [
            'first_name' => '', 'last_name' => 'B', 'phone' => '123', 'email' => $this->member->user->email,
        ])->assertSessionHasErrors(['first_name', 'phone']);
    }

    public function test_banner_and_picture_can_be_uploaded_replaced_and_removed()
    {
        $editor = $this->editor();

        $this->patchSection($editor, 'images', [
            'banner' => UploadedFile::fake()->image('banner.jpg', 1200, 300),
            'photo' => UploadedFile::fake()->image('me.png'),
        ])->assertRedirect();

        $this->member->refresh();
        $first = $this->member->banner_path;
        $this->assertNotNull($first);
        $this->assertNotNull($this->member->photo_path);
        Storage::disk('uploads')->assertExists($first);

        $this->patchSection($editor, 'images', ['banner' => UploadedFile::fake()->image('b2.jpg')])->assertRedirect();
        Storage::disk('uploads')->assertMissing($first);

        $second = $this->member->refresh()->banner_path;
        $this->patchSection($editor, 'images', ['remove_banner' => true])->assertRedirect();

        $this->assertNull($this->member->refresh()->banner_path);
        Storage::disk('uploads')->assertMissing($second);

        $this->assertTrue(Activity::query()->where('description', 'member_updated')->get()->contains(
            fn (Activity $activity) => ($activity->properties['banner_replaced'] ?? false) === true,
        ));
    }

    public function test_the_profile_picture_can_be_removed()
    {
        $editor = $this->editor();
        $this->patchSection($editor, 'images', ['photo' => UploadedFile::fake()->image('me.png')])->assertRedirect();
        $path = $this->member->refresh()->photo_path;
        Storage::disk('uploads')->assertExists($path);

        $this->patchSection($editor, 'images', ['remove_photo' => true])->assertRedirect();

        $this->assertNull($this->member->refresh()->photo_path);
        Storage::disk('uploads')->assertMissing($path);

        $this->patchSection(User::factory()->withPermissions(Permission::MemberView)->create(), 'images', ['remove_photo' => true])
            ->assertForbidden();
    }

    public function test_a_banner_must_be_an_image_and_needs_member_update()
    {
        $this->patchSection($this->editor(), 'images', ['banner' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('banner');

        $this->patchSection(User::factory()->withPermissions(Permission::MemberView)->create(), 'images', [
            'banner' => UploadedFile::fake()->image('b.jpg'),
        ])->assertForbidden();
    }

    public function test_unknown_sections_are_not_found()
    {
        $this->actingAs($this->editor())
            ->patch(route('members.sections.update', [$this->member, 'status']), ['status' => 'rejected'])
            ->assertNotFound();
    }

    public function test_banners_are_served_only_to_those_who_may_view_the_member()
    {
        Storage::disk('uploads')->put('members/banners/b.jpg', 'jpeg');
        $this->member->forceFill(['banner_path' => 'members/banners/b.jpg'])->save();

        $this->actingAs(User::factory()->create())->get(route('members.banner', $this->member))->assertForbidden();
        $this->actingAs($this->member->user)->get(route('members.banner', $this->member))->assertOk();

        $this->member->forceFill(['banner_path' => null])->save();
        $this->actingAs($this->member->user)->get(route('members.banner', $this->member))->assertNotFound();
    }

    public function test_the_profile_tells_the_page_which_tabs_and_edits_the_viewer_has()
    {
        $this->actingAs($this->editor(Permission::MealViewAll))->get(route('members.show', $this->member))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'profile')
                ->where('can.update', true)
                ->where('mealsVisible', true)
                ->where('member.has_banner', false));

        $this->actingAs($this->member->user)->get(route('members.show', $this->member))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.update', false)
                ->where('mealsVisible', true));

        $this->actingAs($this->editor())->get(route('members.show', $this->member))
            ->assertInertia(fn (Assert $page) => $page->where('mealsVisible', false));
    }

    public function test_the_meals_tab_needs_a_meal_view_permission()
    {
        $lunch = MealType::factory()->create(['name' => 'Lunch']);
        Meal::factory()->create(['member_id' => $this->member->id, 'meal_type_id' => $lunch->id, 'meal_date' => '2026-10-05', 'count' => 2]);
        Meal::factory()->create(['member_id' => $this->member->id, 'meal_type_id' => $lunch->id, 'meal_date' => '2026-10-06', 'count' => 1]);
        $url = route('members.show', ['member' => $this->member, 'tab' => 'meals']);

        $this->actingAs($this->editor())->get($url)
            ->assertInertia(fn (Assert $page) => $page->where('meals', null));

        $this->actingAs($this->editor(Permission::MealViewAll))->get($url)
            ->assertInertia(fn (Assert $page) => $page
                ->where('meals.month', '2026-10')
                ->where('meals.total', 3)
                ->has('meals.days', 31)
                ->where('meals.days.4.total', 2)
                ->where('meals.typeTotals.'.$lunch->id, 3));

        $own = User::factory()->withPermissions(Permission::MealViewOwn)->create();
        $this->member->user->givePermissionTo(Permission::MealViewOwn->value);
        $this->actingAs($this->member->user)->get($url)
            ->assertInertia(fn (Assert $page) => $page->where('meals.total', 3));
        $this->actingAs($own)->get($url)->assertForbidden();
    }

    public function test_the_meals_tab_can_show_another_month()
    {
        $lunch = MealType::factory()->create();
        Meal::factory()->create(['member_id' => $this->member->id, 'meal_type_id' => $lunch->id, 'meal_date' => '2026-09-10', 'count' => 4]);

        $this->actingAs($this->editor(Permission::MealViewAll))
            ->get(route('members.show', ['member' => $this->member, 'tab' => 'meals', 'month' => '2026-09']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('meals.month', '2026-09')
                ->where('meals.total', 4)
                ->has('meals.days', 30));
    }

    public function test_the_balance_tab_adds_up_dues_and_what_the_fund_owes()
    {
        Deposit::factory()->approved()->create(['member_id' => $this->member->id, 'amount' => '100.00', 'deposit_date' => '2026-10-02']);
        $purchase = GroceryPurchase::factory()->create(['buyer_member_id' => $this->member->id, 'grand_total' => '400.00', 'purchase_date' => '2026-10-03']);
        BuyerPayable::factory()->create(['grocery_purchase_id' => $purchase->id, 'member_id' => $this->member->id, 'amount' => '400.00', 'amount_reimbursed' => '150.00']);

        $viewer = User::factory()->withPermissions(Permission::MemberView, Permission::ReportViewAll)->create();

        $this->actingAs($viewer)->get(route('members.show', ['member' => $this->member, 'tab' => 'balance']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('balanceVisible', true)
                ->where('balance.fund_owes', '250.00')
                ->where('balance.to_pay', fn ($value) => bccomp((string) $value, '0', 2) >= 0)
                ->where('balance.months.0.month', '2026-10')
                ->where('balance.months.0.deposits', '100.00'));
    }

    public function test_the_balance_tab_is_hidden_without_report_access()
    {
        $this->actingAs($this->editor())->get(route('members.show', ['member' => $this->member, 'tab' => 'balance']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('balanceVisible', false)
                ->where('balance', null));
    }
}
