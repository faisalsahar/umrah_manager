<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SocialAuthController extends Controller
{
    /**
     * The OAuth providers we accept, mapped to a human-readable label.
     *
     * @var array<string, string>
     */
    private const PROVIDERS = [
        'google' => 'Google',
        'github' => 'GitHub',
        'facebook' => 'Facebook',
        'linkedin-openid' => 'LinkedIn',
        'apple' => 'Apple',
        'microsoft' => 'Microsoft',
    ];

    /**
     * Send the user off to the provider's consent screen.
     */
    public function redirect(string $provider): SymfonyRedirect
    {
        abort_unless($this->isEnabled($provider), 404);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the provider's callback: find or create the matching user, then log them in.
     */
    public function callback(string $provider, Request $request, LoginResponse $loginResponse): Response
    {
        abort_unless($this->isEnabled($provider), 404);

        try {
            $oauthUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return $this->failed($provider);
        }

        $email = $oauthUser->getEmail();

        // Some providers (e.g. Apple on later logins) may withhold the email — we can't match a user without it.
        if (! $email) {
            return redirect()->route('login')->withErrors([
                'email' => __(':provider did not share an email address, so we could not sign you in.', [
                    'provider' => self::PROVIDERS[$provider],
                ]),
            ]);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = new User;
            $user->forceFill([
                'name' => $oauthUser->getName() ?: $oauthUser->getNickname() ?: Str::before($email, '@'),
                'email' => $email,
                'provider' => $provider,
                'provider_id' => $oauthUser->getId(),
                'avatar' => $oauthUser->getAvatar(),
                'email_verified_at' => now(),
            ])->save();
        } else {
            // Existing account (e.g. signed up by email first) — backfill any details we don't have yet.
            $user->forceFill([
                'provider' => $user->provider ?: $provider,
                'provider_id' => $user->provider_id ?: $oauthUser->getId(),
                'avatar' => $user->avatar ?: $oauthUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
        }

        Auth::login($user, remember: true);

        // Same destination logic as a password/passkey login — see LoginResponse.
        return $loginResponse->toResponse($request);
    }

    /**
     * Whether the given provider is both known and configured with credentials.
     */
    private function isEnabled(string $provider): bool
    {
        return array_key_exists($provider, self::PROVIDERS)
            && filled(config("services.{$provider}.client_id"));
    }

    /**
     * Redirect back to login with a generic failure message.
     */
    private function failed(string $provider): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'email' => __('We could not sign you in with :provider. Please try again.', [
                'provider' => self::PROVIDERS[$provider],
            ]),
        ]);
    }
}
