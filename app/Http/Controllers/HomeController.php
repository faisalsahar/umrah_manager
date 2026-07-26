<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController
{
    /**
     * Gate the site behind authentication: guests are sent to the login screen,
     * authenticated users land on the dashboard.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if ($request->user()->isAdmin()) {
            return redirect()->route('admin.umrah-companies.index');
        }

        return redirect()->route('dashboard');
    }
}
