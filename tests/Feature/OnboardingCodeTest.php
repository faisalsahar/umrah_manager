<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_is_given_a_unique_onboarding_code(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->assertNotNull($a->onboarding_code);
        $this->assertSame(8, strlen($a->onboarding_code));
        $this->assertNotSame($a->onboarding_code, $b->onboarding_code);
    }

    public function test_the_code_avoids_ambiguous_characters(): void
    {
        // No 0/O or 1/I, so the code can be read out loud over the phone.
        $this->assertMatchesRegularExpression(
            '/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8}$/',
            User::factory()->create()->onboarding_code,
        );
    }

    public function test_an_existing_code_is_not_overwritten(): void
    {
        $user = User::factory()->create();
        $code = $user->onboarding_code;

        $user->update(['name' => 'Renamed']);

        $this->assertSame($code, $user->fresh()->onboarding_code);
        $this->assertSame($code, $user->ensureOnboardingCode());
    }

    public function test_the_first_login_issues_a_one_time_onboarding_notification(): void
    {
        $user = User::factory()->create(['onboarding_notified_at' => null]);

        event(new Login('web', $user, false));

        $user->refresh();
        $this->assertNotNull($user->onboarding_notified_at);
        $this->assertSame(1, $user->notifications()->count());

        $notification = $user->notifications()->first();
        $this->assertSame('onboarding_code', $notification->data['type']);
        $this->assertSame($user->onboarding_code, $notification->data['code']);

        // A second login must not issue it again.
        event(new Login('web', $user, false));

        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    public function test_the_seeded_admin_gets_an_onboarding_code(): void
    {
        $this->seed();

        $admin = User::where('email', config('app.admin_email'))->firstOrFail();

        $this->assertNotNull($admin->onboarding_code);
        $this->assertTrue($admin->isAdmin());
    }
}
