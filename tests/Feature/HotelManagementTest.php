<?php

namespace Tests\Feature;

use App\Enums\HotelCity;
use App\Enums\HotelContractType;
use App\Enums\OrganizationRole;
use App\Models\Hotel;
use App\Models\HotelContract;
use App\Models\Organization;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HotelManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_operator_user_can_create_a_hotel(): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::hotels.index')
            ->set('newNameAr', 'فندق الصفوة رويال')
            ->set('newNameEn', 'Al Safwah Royale')
            ->set('newCity', HotelCity::Makkah->value)
            ->set('newStars', '5')
            ->call('createHotel')
            ->assertHasNoErrors();

        $hotel = Hotel::where('operator_id', $operator->id)->firstOrFail();
        $this->assertSame('Al Safwah Royale', $hotel->name_en);
    }

    public function test_room_types_and_a_contract_with_rates_can_be_added_to_a_hotel(): void
    {
        $operator = Organization::factory()->operator()->create();
        $hotel = Hotel::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);

        $this->actingAs($user);

        $component = Livewire::test('pages::hotels.show', ['hotel' => $hotel])
            ->set('roomTypeName', 'Quad')
            ->set('roomTypeCapacity', '4')
            ->call('addRoomType')
            ->assertHasNoErrors();

        $roomType = RoomType::where('hotel_id', $hotel->id)->firstOrFail();
        $this->assertSame(4, $roomType->capacity);

        $component
            ->set('contractRoomTypeId', (string) $roomType->id)
            ->set('contractType', HotelContractType::Allotment->value)
            ->set('contractStartsOn', now()->addWeek()->format('Y-m-d'))
            ->set('contractEndsOn', now()->addMonth()->format('Y-m-d'))
            ->set('contractReleaseDays', '14')
            ->set('ratePurchase', '90')
            ->set('rateSale', '160')
            ->call('addContract')
            ->assertHasNoErrors();

        $hotel->refresh();
        $contract = $hotel->contracts()->firstOrFail();
        $rate = $contract->rates()->firstOrFail();

        $this->assertSame(9000, $rate->purchase_price_cents);
        $this->assertSame(16000, $rate->sale_price_cents);
        $this->assertSame(
            $contract->starts_on->copy()->subDays(14)->toDateString(),
            $contract->releaseDeadline()->toDateString(),
        );
    }

    public function test_hotels_can_be_bulk_added_from_pasted_rows(): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::hotels.index')
            ->set('bulkRows', "فندق الصفوة, Al Safwah, makkah, 5\nدار التقوى, Dar Al Taqwa, madinah\n\nbad line")
            ->call('bulkAddHotels');

        $this->assertSame(2, Hotel::where('operator_id', $operator->id)->count());
        $this->assertSame('Al Safwah', Hotel::where('name_en', 'Al Safwah')->firstOrFail()->name_en);
    }

    public function test_an_admin_can_confirm_and_archive_a_hotel(): void
    {
        $operator = Organization::factory()->operator()->create();
        $hotel = Hotel::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        $this->assertFalse($hotel->isConfirmed());

        Livewire::test('pages::hotels.index')->call('confirmHotel', $hotel->id);
        $this->assertTrue($hotel->fresh()->isConfirmed());

        Livewire::test('pages::hotels.index')->call('archiveHotel', $hotel->id);
        $this->assertSoftDeleted($hotel);

        Livewire::test('pages::hotels.index')->set('showArchived', true)->call('restoreHotel', $hotel->id);
        $this->assertNull($hotel->fresh()->deleted_at);
    }

    public function test_a_data_entry_user_cannot_confirm_or_archive_a_hotel(): void
    {
        $operator = Organization::factory()->operator()->create();
        $hotel = Hotel::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::DataEntry);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::hotels.index')->call('confirmHotel', $hotel->id)->assertStatus(403);
        Livewire::test('pages::hotels.index')->call('archiveHotel', $hotel->id)->assertStatus(403);
    }

    public function test_a_user_cannot_confirm_another_operators_hotel(): void
    {
        $operatorA = Organization::factory()->operator()->create();
        $operatorB = Organization::factory()->operator()->create();
        $foreignHotel = Hotel::factory()->create(['operator_id' => $operatorB->id]);

        $user = User::factory()->create();
        $operatorA->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operatorA->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::hotels.index')->call('confirmHotel', $foreignHotel->id)->assertStatus(404);
        $this->assertFalse($foreignHotel->fresh()->isConfirmed());
    }

    public function test_a_stranger_cannot_manage_another_operators_hotel(): void
    {
        $hotel = Hotel::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger);

        Livewire::test('pages::hotels.show', ['hotel' => $hotel])->assertStatus(403);
    }

    public function test_a_contract_with_no_release_days_has_no_release_deadline(): void
    {
        $contract = HotelContract::factory()->create(['release_days' => null]);

        $this->assertNull($contract->releaseDeadline());
    }
}
