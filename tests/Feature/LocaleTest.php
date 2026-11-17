<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_choose_a_supported_language_and_it_is_remembered(): void
    {
        $this->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'bn'])
            ->assertRedirect(route('login'))
            ->assertCookie('locale', 'bn', false);
    }

    public function test_an_unsupported_language_is_refused(): void
    {
        $this->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale')
            ->assertCookieMissing('locale');
    }

    public function test_the_chosen_language_is_used_for_the_page_and_its_translations(): void
    {
        $this->withUnencryptedCookie('locale', 'bn')
            ->get(route('login'))
            ->assertOk()
            ->assertSee('<html lang="bn"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'bn')
                ->where('locales', ['en' => 'English', 'bn' => 'বাংলা'])
                ->where('translations.Dashboard', 'ড্যাশবোর্ড'));
    }

    public function test_english_is_used_without_a_choice_or_with_an_unknown_one(): void
    {
        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));

        $this->withUnencryptedCookie('locale', 'xx')
            ->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }
}
