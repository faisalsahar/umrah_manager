<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\OperatingOrder;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A full HTTP smoke pass over every screen added in this phase — Livewire
 * component tests already cover behaviour, this just confirms each route
 * renders end to end (layout, translations, RTL) in both locales without
 * crashing on a missing view/translation/relation.
 */
class Phase7SmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    public static function routeProvider(): array
    {
        return [
            ['geography.index'],
            ['movement-types.index'],
            ['permit-types.index'],
            ['hotels.index'],
            ['operating-orders.create'],
            ['organizations.index'],
            ['movements.index'],
        ];
    }

    #[DataProvider('routeProvider')]
    public function test_route_renders_in_english(string $routeName): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create(['locale' => 'en']);
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user)->get(route($routeName))->assertOk();
    }

    #[DataProvider('routeProvider')]
    public function test_route_renders_in_arabic(string $routeName): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create(['locale' => 'ar']);
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user)->get(route($routeName))
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }

    /**
     * @return list<array{string, string, string}>
     */
    public static function translatedPageProvider(): array
    {
        return [
            ['geography.index', 'Geography', 'الجغرافيا'],
            ['movement-types.index', 'Movement Types', 'أنواع التحركات'],
            ['permit-types.index', 'Permit Types', 'أنواع التصاريح'],
            ['hotels.index', 'Hotels', 'الفنادق'],
            ['operating-orders.create', 'New Operating Order', 'أمر تشغيل جديد'],
            ['organizations.index', 'Organizations', 'المؤسسات'],
            ['movements.index', 'Daily Movements', 'التحركات اليومية'],
        ];
    }

    /**
     * Confirms actual translated content renders — not just that the page loads —
     * and that the English source string never leaks through when Arabic is active.
     * The page <title> comes from Livewire's #[Title(...)] attribute, a
     * compile-time constant that can't call __() directly, so this exercises the
     * same head.blade.php translation path covered for the dashboard, across every
     * screen this phase added or touched.
     */
    #[DataProvider('translatedPageProvider')]
    public function test_page_title_translates_correctly(string $routeName, string $english, string $arabic): void
    {
        $operator = Organization::factory()->operator()->create();

        $englishUser = User::factory()->create(['locale' => 'en']);
        $operator->addMember($englishUser, OrganizationRole::Admin);
        $englishUser->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($englishUser)->get(route($routeName))
            ->assertOk()
            ->assertSee($english)
            ->assertDontSee($arabic);

        $arabicUser = User::factory()->create(['locale' => 'ar']);
        $operator->addMember($arabicUser, OrganizationRole::Admin);
        $arabicUser->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($arabicUser)->get(route($routeName))
            ->assertOk()
            ->assertSee($arabic)
            ->assertDontSee('>'.$english.'<', false);
    }

    public function test_operating_order_show_renders_with_full_logistics_in_both_locales(): void
    {
        $operator = Organization::factory()->operator()->create();
        $order = OperatingOrder::factory()->create(['operator_id' => $operator->id]);

        foreach (['en', 'ar'] as $locale) {
            $user = User::factory()->create(['locale' => $locale]);
            $operator->addMember($user, OrganizationRole::Admin);
            $user->forceFill(['current_organization_id' => $operator->id])->save();

            $this->actingAs($user)->get(route('operating-orders.show', $order))->assertOk();
        }
    }
}
