<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\ContractAllotment;
use App\Models\Hotel;
use App\Models\HotelContract;
use App\Models\HotelStay;
use App\Models\Organization;
use App\Models\Pilgrim;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HotelAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsOperatorAdmin(Organization $operator): User
    {
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();
        $this->actingAs($user);

        return $user;
    }

    public function test_a_user_from_another_operator_cannot_view_the_grid(): void
    {
        $operator = Organization::factory()->operator()->create();
        $hotel = Hotel::factory()->create(['operator_id' => $operator->id]);

        $otherOperator = Organization::factory()->operator()->create();
        $this->actingAsOperatorAdmin($otherOperator);

        Livewire::test('pages::hotels.availability', ['hotel' => $hotel])->assertStatus(403);
    }

    public function test_a_night_with_no_allotment_shows_as_unallotted(): void
    {
        $operator = Organization::factory()->operator()->create();
        $hotel = Hotel::factory()->create(['operator_id' => $operator->id]);
        $roomType = RoomType::factory()->create(['hotel_id' => $hotel->id, 'capacity' => 4]);

        $this->actingAsOperatorAdmin($operator);

        $grid = Livewire::test('pages::hotels.availability', ['hotel' => $hotel])->get('grid');

        $tonight = now()->startOfDay()->format('Y-m-d');
        $this->assertSame(0, $grid[$roomType->id][$tonight]['allotted']);
        $this->assertSame(0, $grid[$roomType->id][$tonight]['occupied_rooms']);
    }

    public function test_the_grid_reports_available_full_and_overbooked_nights(): void
    {
        $operator = Organization::factory()->operator()->create();
        $hotel = Hotel::factory()->create(['operator_id' => $operator->id]);
        $roomType = RoomType::factory()->create(['hotel_id' => $hotel->id, 'capacity' => 4]);
        $contract = HotelContract::factory()->create([
            'hotel_id' => $hotel->id,
            'operator_id' => $operator->id,
            'starts_on' => now()->startOfDay(),
            'ends_on' => now()->addDays(30),
        ]);

        $availableNight = now()->startOfDay()->addDay();
        $fullNight = now()->startOfDay()->addDays(2);
        $overbookedNight = now()->startOfDay()->addDays(3);

        foreach ([$availableNight, $fullNight, $overbookedNight] as $night) {
            ContractAllotment::factory()->create([
                'hotel_contract_id' => $contract->id,
                'room_type_id' => $roomType->id,
                'date' => $night,
                'rooms_allotted' => 2,
            ]);
        }

        // Available: 1 bed occupied out of 2 rooms' worth of capacity (8 beds).
        $this->stayFor($operator, $hotel, $roomType, $availableNight);

        // Full: exactly 8 beds = 2 rooms, matching the 2-room allotment.
        for ($i = 0; $i < 8; $i++) {
            $this->stayFor($operator, $hotel, $roomType, $fullNight);
        }

        // Overbooked: 12 beds = 3 rooms, past the 2-room allotment.
        for ($i = 0; $i < 12; $i++) {
            $this->stayFor($operator, $hotel, $roomType, $overbookedNight);
        }

        $this->actingAsOperatorAdmin($operator);

        $grid = Livewire::test('pages::hotels.availability', ['hotel' => $hotel])->get('grid');

        $this->assertSame(1, $grid[$roomType->id][$availableNight->format('Y-m-d')]['occupied_rooms']);
        $this->assertSame(2, $grid[$roomType->id][$fullNight->format('Y-m-d')]['occupied_rooms']);
        $this->assertSame(3, $grid[$roomType->id][$overbookedNight->format('Y-m-d')]['occupied_rooms']);
    }

    public function test_clicking_a_cell_reveals_the_pilgrims_booked_into_it(): void
    {
        $operator = Organization::factory()->operator()->create();
        $hotel = Hotel::factory()->create(['operator_id' => $operator->id]);
        $roomType = RoomType::factory()->create(['hotel_id' => $hotel->id, 'capacity' => 4]);
        $night = now()->startOfDay()->addDay();

        $pilgrim = Pilgrim::factory()->create(['operator_id' => $operator->id, 'name' => 'Ahmed Osman']);
        HotelStay::factory()->create([
            'hotel_id' => $hotel->id,
            'room_type_id' => $roomType->id,
            'pilgrim_id' => $pilgrim->id,
            'check_in' => $night,
            'check_out' => $night->copy()->addDay(),
        ]);

        $this->actingAsOperatorAdmin($operator);

        Livewire::test('pages::hotels.availability', ['hotel' => $hotel])
            ->call('showDetail', $roomType->id, $night->format('Y-m-d'))
            ->assertSee('Ahmed Osman');
    }

    public function test_the_mobile_room_type_selector_defaults_to_the_first_room_type_and_can_switch(): void
    {
        $operator = Organization::factory()->operator()->create();
        $hotel = Hotel::factory()->create(['operator_id' => $operator->id]);
        $double = RoomType::factory()->create(['hotel_id' => $hotel->id, 'name' => 'Double', 'capacity' => 2]);
        $quad = RoomType::factory()->create(['hotel_id' => $hotel->id, 'name' => 'Quad', 'capacity' => 4]);

        $this->actingAsOperatorAdmin($operator);

        $component = Livewire::test('pages::hotels.availability', ['hotel' => $hotel]);

        $this->assertSame($double->id, $component->get('mobileRoomTypeId'));
        $this->assertSame($double->id, $component->get('mobileRoomType')->id);

        $component->set('mobileRoomTypeId', $quad->id);
        $this->assertSame($quad->id, $component->get('mobileRoomType')->id);
    }

    public function test_clicking_an_empty_cell_shows_the_empty_state_without_erroring(): void
    {
        $operator = Organization::factory()->operator()->create();
        $hotel = Hotel::factory()->create(['operator_id' => $operator->id]);
        $roomType = RoomType::factory()->create(['hotel_id' => $hotel->id]);

        $this->actingAsOperatorAdmin($operator);

        Livewire::test('pages::hotels.availability', ['hotel' => $hotel])
            ->call('showDetail', $roomType->id, now()->addDay()->format('Y-m-d'))
            ->assertOk()
            ->assertSee('No one booked into this room type that night.');
    }

    private function stayFor(Organization $operator, Hotel $hotel, RoomType $roomType, $night): void
    {
        $pilgrim = Pilgrim::factory()->create(['operator_id' => $operator->id]);

        HotelStay::factory()->create([
            'hotel_id' => $hotel->id,
            'room_type_id' => $roomType->id,
            'pilgrim_id' => $pilgrim->id,
            'check_in' => $night,
            'check_out' => $night->copy()->addDay(),
        ]);
    }
}
