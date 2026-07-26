<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;

/**
 * Guards a "secure area" like Fortify's password.confirm middleware, but never
 * prompts accounts that have no password (e.g. OAuth/social logins) — for them
 * there is nothing to confirm, so requiring it would trap them on the
 * confirm-password screen after the confirmation window expires.
 */
class ConfirmPasswordUnlessPasswordless extends RequirePassword
{
    /**
     * Determine if the confirmation timeout has expired.
     */
    protected function shouldConfirmPassword($request, $passwordTimeoutSeconds = null): bool
    {
        if ($this->isPasswordless($request)) {
            return false;
        }

        return parent::shouldConfirmPassword($request, $passwordTimeoutSeconds);
    }

    /**
     * Whether the current user signed in without ever setting a password.
     */
    private function isPasswordless(Request $request): bool
    {
        return blank($request->user()?->getAuthPassword());
    }
}
