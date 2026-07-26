<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\OnboardingCodeNotification;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Apple\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSocialite();
        $this->issueOnboardingCodeOnLogin();
        $this->ensureCurrentOrganizationOnLogin();
        $this->rememberLocaleCookieOnLogin();
    }

    /**
     * The first time a user logs in, make sure they have an onboarding code and issue a
     * one-time notification telling them what it is.
     */
    protected function issueOnboardingCodeOnLogin(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;

            if (! $user instanceof User || $user->onboarding_notified_at !== null) {
                return;
            }

            $user->notify(new OnboardingCodeNotification($user->ensureOnboardingCode()));

            // Mark as notified so a second login never re-issues it.
            $user->forceFill(['onboarding_notified_at' => now()])->save();
        });
    }

    /**
     * A user's "current organization" is the context every screen scopes to (see
     * User::currentOrganization()). Rather than every page having to handle "what if
     * it's not set yet", we guarantee it here at login time — defaulting to the
     * user's first membership, or leaving it null if they belong to nowhere.
     */
    protected function ensureCurrentOrganizationOnLogin(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->ensureCurrentOrganization();
            }
        });
    }

    /**
     * Keeps a long-lived `locale` cookie in sync with the user's saved language
     * preference on every login — this is what lets the *guest-facing* login
     * screen itself show up in a returning user's own language (see
     * SetLocale middleware), even though a guest has no user record to read
     * a preference from. Users who never touched the language setting have
     * no preference to remember, so nothing is queued for them.
     */
    protected function rememberLocaleCookieOnLogin(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User && $event->user->locale !== null) {
                Cookie::queue(Cookie::forever('locale', $event->user->locale));
            }
        });
    }

    /**
     * Register the Socialite drivers that ship as separate provider packages.
     */
    protected function configureSocialite(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('apple', Provider::class);
            $event->extendSocialite('microsoft', \SocialiteProviders\Microsoft\Provider::class);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
