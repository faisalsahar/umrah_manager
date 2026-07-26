<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-secret');
    }

    /**
     * Stand in for the account the OAuth provider hands back.
     */
    private function fakeProviderUser(string $email, string $name = 'Ada Lovelace'): void
    {
        $oauthUser = Mockery::mock(SocialiteUser::class);
        $oauthUser->shouldReceive('getEmail')->andReturn($email);
        $oauthUser->shouldReceive('getName')->andReturn($name);
        $oauthUser->shouldReceive('getNickname')->andReturn(null);
        $oauthUser->shouldReceive('getId')->andReturn('provider-id-123');
        $oauthUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        Socialite::shouldReceive('driver->user')->andReturn($oauthUser);
    }

    public function test_configured_provider_redirects_to_the_consent_screen(): void
    {
        $this->get('/auth/google/redirect')->assertRedirect();
    }

    public function test_unconfigured_provider_is_not_available(): void
    {
        config()->set('services.apple.client_id', null);

        $this->get('/auth/apple/redirect')->assertNotFound();
    }

    public function test_unknown_provider_is_not_available(): void
    {
        $this->get('/auth/myspace/redirect')->assertNotFound();
    }

    public function test_callback_creates_and_logs_in_a_new_user(): void
    {
        $this->fakeProviderUser('ada@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $user = User::where('email', 'ada@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google', $user->provider);
        $this->assertSame('provider-id-123', $user->provider_id);
        $this->assertNotNull($user->email_verified_at);
        // Social sign-ups never set a password.
        $this->assertNull($user->password);
    }

    public function test_callback_sends_the_admin_to_the_umrah_companies_panel_instead_of_the_dashboard(): void
    {
        $this->fakeProviderUser(config('app.admin_email', 'info@computerize.biz'));

        $this->get('/auth/google/callback')->assertRedirect(route('admin.umrah-companies.index'));
    }

    public function test_callback_links_an_existing_account_by_email(): void
    {
        $existing = User::factory()->create([
            'email' => 'ada@example.com',
            'provider' => null,
        ]);

        $this->fakeProviderUser('ada@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::where('email', 'ada@example.com')->count());
        $this->assertSame('google', $existing->fresh()->provider);
    }

    public function test_provider_without_an_email_cannot_sign_in(): void
    {
        $this->fakeProviderUser('');

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_passwordless_user_reaches_security_settings_without_confirming_a_password(): void
    {
        // An OAuth user has no password, so there is nothing to confirm — requiring
        // it would strand them on the confirm-password screen.
        $user = User::factory()->create(['password' => null, 'provider' => 'google']);

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertOk();
    }

    public function test_password_user_must_still_confirm_their_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));
    }
}
