<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Where a signed-in user lands right after authenticating — bound over Fortify's
 * default so every login path (password, two-factor, passkey, and the OAuth
 * callback in SocialAuthController, which resolves this same contract) agrees on
 * the destination. The platform admin is never a member of any Umrah company
 * (see docs/PLAN.md), so sending them to /dashboard would just show an empty
 * "you don't belong to an organization" state — they go to their own panel instead.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        if (Auth::user()?->isAdmin()) {
            return redirect()->intended(route('admin.umrah-companies.index'));
        }

        return redirect()->intended(route('dashboard'));
    }
}
