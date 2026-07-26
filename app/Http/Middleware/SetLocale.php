<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the signed-in user's preferred interface language (see
 * User::$locale and the language settings screen). A guest has no user
 * record to read a preference from, but if this browser belongs to someone
 * who previously chose a language (either by saving it while signed in, or
 * simply by having signed in before — see AppServiceProvider's
 * rememberLocaleCookieOnLogin()), the `locale` cookie remembers it, so even
 * the login screen itself shows up in their language. A browser that has
 * never seen a preference falls back to the application default.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locale = ($user instanceof User ? $user->locale : null) ?? $request->cookie('locale');

        if (is_string($locale) && array_key_exists($locale, config('azowar.locales', []))) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
