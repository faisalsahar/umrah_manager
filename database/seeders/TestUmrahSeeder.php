<?php

namespace Database\Seeders;

use App\Enums\HotelStaySource;
use App\Enums\HotelStayStatus;
use App\Enums\MovementSource;
use App\Enums\MovementStatus;
use App\Enums\OperatingOrderStatus;
use App\Enums\OrderLineType;
use App\Enums\OrganizationRole;
use App\Models\City;
use App\Models\Group;
use App\Models\Hotel;
use App\Models\HotelStay;
use App\Models\Location;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\OperatingOrder;
use App\Models\OrderLine;
use App\Models\Organization;
use App\Models\Pilgrim;
use App\Models\RoomType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\OperatorDefaults;
use Illuminate\Database\Seeder;

/**
 * A brand-new Umrah company ("Test Umrah") built the same way the admin panel
 * builds one for real — Organization + OperatorDefaults::seed() — then run
 * through one full order end to end using only that starter catalog (no
 * hand-picked extras like UmrahDemoSeeder uses). Exists to prove the "easy to
 * set up" starter catalog is actually enough to operate on immediately.
 */
class TestUmrahSeeder extends Seeder
{
    public function run(): void
    {
        $operator = Organization::factory()->operator()->create([
            'name_ar' => 'تجربة عمرة',
            'name_en' => 'Test Umrah',
            'license_no' => 'MISA-TEST-0001',
        ]);

        OperatorDefaults::seed($operator);

        $owner = User::factory()->create(['name' => 'Test Owner', 'email' => 'owner@test-umrah.test']);
        $operator->addMember($owner, OrganizationRole::Owner);
        $owner->forceFill(['current_organization_id' => $operator->id])->save();

        $agent = Organization::factory()->agent()->create(['name_ar' => 'وكيل تجريبي', 'name_en' => 'Test Agent', 'country' => 'SD']);
        $agentUser = User::factory()->create(['name' => 'Test Agent User', 'email' => 'agent@test-umrah.test']);
        $agent->addMember($agentUser, OrganizationRole::AgentUser);
        $agentUser->forceFill(['current_organization_id' => $agent->id])->save();

        // --- Everything below draws only from what OperatorDefaults just seeded —
        //     proof that a brand-new company can open and run a real order without
        //     adding anything first. ---
        $jeddah = City::where('operator_id', $operator->id)->where('name_en', 'Jeddah')->firstOrFail();
        $madinah = City::where('operator_id', $operator->id)->where('name_en', 'Madinah')->firstOrFail();
        $jeddahAirport = Location::where('operator_id', $operator->id)->where('city_id', $jeddah->id)->firstOrFail();
        $madinahAirport = Location::where('operator_id', $operator->id)->where('city_id', $madinah->id)->firstOrFail();

        $arrivalType = MovementType::where('operator_id', $operator->id)->where('name_en', 'Arrival')->firstOrFail();
        $departureType = MovementType::where('operator_id', $operator->id)->where('name_en', 'Departure')->firstOrFail();

        $busType = VehicleType::where('operator_id', $operator->id)->where('name', 'Bus - 49 Seater')->firstOrFail();
        $bus = Vehicle::factory()->create([
            'operator_id' => $operator->id,
            'vehicle_type_id' => $busType->id,
            'transport_supplier_id' => null,
            'plate_no' => 'TST 0001',
        ]);

        $makkahHotel = Hotel::where('operator_id', $operator->id)->where('name_en', 'Makkah Hotel (rename me)')->firstOrFail();
        $makkahQuad = RoomType::where('hotel_id', $makkahHotel->id)->firstOrFail();

        $group = Group::factory()->create([
            'operator_id' => $operator->id,
            'agent_id' => $agent->id,
            'name' => 'Test Group 001',
            'arrival_date' => now()->addDays(14),
            'departure_date' => now()->addDays(21),
        ]);

        $pilgrims = collect([
            Pilgrim::factory()->male()->create(['operator_id' => $operator->id, 'agent_id' => $agent->id, 'group_id' => $group->id, 'name' => 'Test Pilgrim One']),
            Pilgrim::factory()->female()->create(['operator_id' => $operator->id, 'agent_id' => $agent->id, 'group_id' => $group->id, 'name' => 'Test Pilgrim Two']),
        ]);

        $order = OperatingOrder::factory()->create([
            'operator_id' => $operator->id,
            'agent_id' => $agent->id,
            'group_id' => $group->id,
            'entered_by_user_id' => $owner->id,
            'status' => OperatingOrderStatus::Approved,
            'approved_at' => now(),
            'adults_count' => $pilgrims->count(),
            'children_count' => 0,
            'infants_count' => 0,
            'arrival_date' => $group->arrival_date,
            'arrival_city_id' => $jeddah->id,
            'arrival_port_location_id' => $jeddahAirport->id,
            'arrival_carrier' => 'Saudia',
            'arrival_flight_no' => 'SV 900',
            'departure_date' => $group->departure_date,
            'departure_city_id' => $madinah->id,
            'departure_port_location_id' => $madinahAirport->id,
            'departure_carrier' => 'Saudia',
            'departure_flight_no' => 'SV 905',
        ]);

        $arrival = Movement::factory()->create([
            'operating_order_id' => $order->id, 'vehicle_id' => $bus->id,
            'movement_type_id' => $arrivalType->id, 'source' => MovementSource::OperatorProvided,
            'status' => MovementStatus::Confirmed,
            'from_location' => 'Jeddah Airport', 'to_location' => 'Makkah Hotel',
            'movement_at' => $group->arrival_date, 'pilgrim_count' => $pilgrims->count(),
        ]);
        $departure = Movement::factory()->create([
            'operating_order_id' => $order->id, 'vehicle_id' => $bus->id,
            'movement_type_id' => $departureType->id, 'source' => MovementSource::OperatorProvided,
            'status' => MovementStatus::Pending,
            'from_location' => 'Makkah Hotel', 'to_location' => 'Madinah Airport',
            'movement_at' => $group->departure_date, 'pilgrim_count' => $pilgrims->count(),
        ]);
        foreach ([$arrival, $departure] as $movement) {
            $movement->pilgrims()->attach($pilgrims->pluck('id'));
        }

        foreach ($pilgrims as $pilgrim) {
            $stay = HotelStay::factory()->create([
                'operating_order_id' => $order->id,
                'hotel_id' => $makkahHotel->id,
                'room_type_id' => $makkahQuad->id,
                'pilgrim_id' => $pilgrim->id,
                'check_in' => $group->arrival_date,
                'check_out' => $group->departure_date,
                'status' => HotelStayStatus::Booked,
                'source' => HotelStaySource::OperatorSold,
            ]);

            OrderLine::create([
                'operating_order_id' => $order->id,
                'line_type' => OrderLineType::Hotel,
                'hotel_stay_id' => $stay->id,
                'description' => "Makkah — {$makkahQuad->name} bed — {$pilgrim->name}",
                'quantity' => 7,
                'purchase_price_cents' => 9000,
                'sale_price_cents' => 16000,
                'currency' => 'SAR',
            ]);
        }

        foreach ([[$arrival, 'Arrival transfer'], [$departure, 'Departure transfer']] as [$movement, $description]) {
            OrderLine::create([
                'operating_order_id' => $order->id,
                'line_type' => OrderLineType::Transport,
                'movement_id' => $movement->id,
                'description' => $description,
                'quantity' => 1,
                'purchase_price_cents' => 100000,
                'sale_price_cents' => 150000,
                'currency' => 'SAR',
            ]);
        }
    }
}
