<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events are intentionally NOT suppressed (no WithoutModelEvents): the seeded
     * user must come out fully formed, like a real registration — in particular the
     * `creating` hook is what assigns the onboarding code.
     */
    public function run(): void
    {
        // The admin — see config('app.admin_email') and User::isAdmin().
        // Idempotent: keeps an existing account's name, but (re)sets the password, so the
        // seeder doubles as the way to recover access to the account.
        $admin = User::firstOrNew(['email' => config('app.admin_email', 'info@computerize.biz')]);
        $admin->name = $admin->name ?: 'Admin';
        $admin->email_verified_at ??= now();
        $admin->password = config('app.admin_password', 'D25ddeaf16@');
        $admin->save();

        $this->call(UmrahDemoSeeder::class);
        $this->call(TestUmrahSeeder::class);
    }
}
