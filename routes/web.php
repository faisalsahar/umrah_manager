<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Social (OAuth) login — guest-accessible.
Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
Route::match(['get', 'post'], 'auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('organizations', 'pages::organizations.index')->name('organizations.index');

    Route::livewire('groups', 'pages::groups.index')->name('groups.index');
    Route::livewire('groups/{group}', 'pages::groups.show')->name('groups.show');
    Route::livewire('groups/{group}/import', 'pages::groups.import')->name('groups.import');

    Route::livewire('my-pilgrims', 'pages::my-pilgrims.index')->name('my-pilgrims.index');

    Route::livewire('sales', 'pages::sales.index')->name('sales.index');
    Route::livewire('sales/{sale}', 'pages::sales.show')->name('sales.show');

    Route::livewire('operating-orders', 'pages::operating-orders.index')->name('operating-orders.index');
    Route::livewire('operating-orders/create', 'pages::operating-orders.create')->name('operating-orders.create');
    Route::livewire('operating-orders/{operatingOrder}', 'pages::operating-orders.show')->name('operating-orders.show');

    Route::livewire('movements', 'pages::movements.index')->name('movements.index');

    Route::livewire('hotels', 'pages::hotels.index')->name('hotels.index');
    Route::livewire('hotels/{hotel}', 'pages::hotels.show')->name('hotels.show');
    Route::livewire('hotels/{hotel}/availability', 'pages::hotels.availability')->name('hotels.availability');

    Route::livewire('vehicles', 'pages::vehicles.index')->name('vehicles.index');

    Route::livewire('services', 'pages::services.index')->name('services.index');

    Route::livewire('geography', 'pages::geography.index')->name('geography.index');
    Route::livewire('movement-types', 'pages::movement-types.index')->name('movement-types.index');
    Route::livewire('permit-types', 'pages::permit-types.index')->name('permit-types.index');

    // The platform admin's own area — creating and overseeing Umrah companies.
    // Everything inside a company is the company's own business, see docs/PLAN.md.
    Route::livewire('admin/umrah-companies', 'pages::admin.umrah-companies.index')->name('admin.umrah-companies.index');
    Route::livewire('admin/umrah-companies/{umrahCompany}', 'pages::admin.umrah-companies.show')->name('admin.umrah-companies.show');
});

require __DIR__.'/settings.php';
