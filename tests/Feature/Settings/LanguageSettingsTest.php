<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use Tests\TestCase;

class LanguageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_switch_their_language_to_arabic(): void
    {
        $user = User::factory()->create(['locale' => null]);
        $this->actingAs($user);

        Livewire::test('pages::settings.language')
            ->set('locale', 'ar')
            ->assertRedirect(route('language.edit'));

        $this->assertSame('ar', $user->fresh()->locale);
    }

    public function test_an_invalid_locale_is_rejected(): void
    {
        $user = User::factory()->create(['locale' => null]);
        $this->actingAs($user);

        Livewire::test('pages::settings.language')->set('locale', 'xx-not-real');

        $this->assertNull($user->fresh()->locale);
    }

    public function test_the_middleware_applies_the_users_locale_to_the_request(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);
        $this->actingAs($user);

        $this->get(route('dashboard'));

        $this->assertSame('ar', app()->getLocale());
    }

    public function test_a_guest_or_user_with_no_locale_preference_gets_the_app_default(): void
    {
        $user = User::factory()->create(['locale' => null]);
        $this->actingAs($user);

        $this->get(route('dashboard'));

        $this->assertSame(config('app.locale'), app()->getLocale());
    }

    public function test_logging_in_does_not_override_an_explicit_locale_preference(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);

        event(new Login('web', $user, false));

        $this->assertSame('ar', $user->fresh()->locale);
    }

    public function test_the_arabic_locale_renders_the_page_right_to_left(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertSee('dir="rtl"', false);
    }

    public function test_the_english_locale_renders_the_page_left_to_right(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertSee('dir="ltr"', false);
    }

    public function test_arabic_locale_translates_page_content_and_the_browser_title(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        // The page title comes from Livewire's #[Title('Dashboard')] attribute — a
        // compile-time constant that can't call __() directly, so translation
        // happens where it's finally rendered (see partials/head.blade.php).
        $response->assertSee('لوحة التحكم');
        $response->assertDontSee('>Dashboard<', false);
    }

    public function test_english_locale_keeps_the_browser_title_in_english(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertSee('<title>', false)->assertSee('Dashboard');
    }

    public function test_a_guest_with_a_remembered_locale_cookie_sees_the_login_page_in_that_language(): void
    {
        $this->withUnencryptedCookie('locale', 'ar')
            ->get(route('login'))
            ->assertSee('dir="rtl"', false);
    }

    public function test_a_guest_with_no_locale_cookie_sees_the_app_default_login_page(): void
    {
        $this->get(route('login'))->assertSee('dir="ltr"', false);
    }

    public function test_logging_in_queues_a_locale_cookie_matching_the_users_preference(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);

        event(new Login('web', $user, false));

        $this->assertSame('ar', Cookie::queued('locale')->getValue());
    }

    public function test_logging_in_queues_no_locale_cookie_when_the_user_has_no_preference(): void
    {
        $user = User::factory()->create(['locale' => null]);

        event(new Login('web', $user, false));

        $this->assertNull(Cookie::queued('locale'));
    }

    public function test_switching_language_in_settings_queues_the_locale_cookie_immediately(): void
    {
        $user = User::factory()->create(['locale' => null]);
        $this->actingAs($user);

        Livewire::test('pages::settings.language')->set('locale', 'ar');

        $this->assertSame('ar', Cookie::queued('locale')->getValue());
    }
}
