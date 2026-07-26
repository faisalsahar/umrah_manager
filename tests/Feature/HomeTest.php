<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_are_sent_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('home'))->assertRedirect(route('dashboard'));
    }

    public function test_the_admin_is_sent_to_the_umrah_companies_panel_instead(): void
    {
        $admin = User::factory()->create(['email' => config('app.admin_email', 'info@computerize.biz')]);
        $this->actingAs($admin);

        $this->get(route('home'))->assertRedirect(route('admin.umrah-companies.index'));
    }
}
