<?php

namespace Database\Seeders;

use App\Enums\AgentSalePaymentDirection;
use App\Enums\AgentSaleStatus;
use App\Enums\AgentSaleType;
use App\Enums\HotelCity;
use App\Enums\HotelContractType;
use App\Enums\HotelStaySource;
use App\Enums\HotelStayStatus;
use App\Enums\LedgerEntryType;
use App\Enums\LocationType;
use App\Enums\MovementCategory;
use App\Enums\MovementSource;
use App\Enums\MovementStatus;
use App\Enums\OperatingOrderStatus;
use App\Enums\OrderLineType;
use App\Enums\OrganizationRole;
use App\Enums\ServiceType;
use App\Enums\TripMode;
use App\Models\AgentSale;
use App\Models\City;
use App\Models\Group;
use App\Models\Hotel;
use App\Models\HotelContract;
use App\Models\HotelStay;
use App\Models\LedgerEntry;
use App\Models\Location;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\OperatingOrder;
use App\Models\OrderLine;
use App\Models\Organization;
use App\Models\PermitType;
use App\Models\Pilgrim;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * A single, coherent demo scenario walking the whole domain end to end: an operator,
 * two agents, a hotel in each city, a transport fleet, a group of pilgrims, and one
 * operating order carrying real movements, hotel stays, order lines and ledger
 * entries — including one stay the operator never sold (the agent's own hotel
 * booking), to demonstrate the intake path described in docs/PLAN.md §3.
 */
class UmrahDemoSeeder extends Seeder
{
    public function run(): void
    {
        $operator = Organization::factory()->operator()->create([
            'name_ar' => 'شركة الرحمة لخدمات العمرة',
            'name_en' => 'Al-Rahma Umrah Services',
            'license_no' => 'MISA-UMR-10234',
        ]);

        $agentSudan = Organization::factory()->agent()->create([
            'name_ar' => 'وكالة السودان للسفر',
            'name_en' => 'Sudan Travel Agency',
            'country' => 'SD',
        ]);
        $agentSudanSub = Organization::factory()->agent()->create([
            'name_ar' => 'وكيل الخرطوم الفرعي',
            'name_en' => 'Khartoum Sub-Agent',
            'country' => 'SD',
            'parent_organization_id' => $agentSudan->id,
        ]);
        $agentTurkiye = Organization::factory()->agent()->create([
            'name_ar' => 'جولات تركيا للعمرة',
            'name_en' => 'Türkiye Umrah Tours',
            'country' => 'TR',
        ]);
        $transportSupplier = Organization::factory()->transportSupplier()->create([
            'name_ar' => 'شركة الحرمين للنقل',
            'name_en' => 'Haramain Transport Co.',
        ]);

        $this->addStaff($operator, $agentSudan);

        // --- Fleet: one bus in the operator's own fleet, one from a contracted supplier. ---
        $busType = VehicleType::factory()->create(['operator_id' => $operator->id, 'name' => 'Bus - 49 Seater', 'capacity' => 49]);
        $ownBus = Vehicle::factory()->create([
            'operator_id' => $operator->id,
            'vehicle_type_id' => $busType->id,
            'transport_supplier_id' => null,
            'plate_no' => 'ABC 1234',
        ]);
        Vehicle::factory()->create([
            'operator_id' => $operator->id,
            'vehicle_type_id' => $busType->id,
            'transport_supplier_id' => $transportSupplier->id,
            'plate_no' => 'XYZ 5678',
        ]);

        // --- Hotels: one per city, with an allotment contract and seasonal rates. ---
        $makkahHotel = Hotel::factory()->create([
            'operator_id' => $operator->id,
            'name_ar' => 'فندق الصفوة رويال',
            'name_en' => 'Al Safwah Royale',
            'city' => HotelCity::Makkah,
        ]);
        $madinahHotel = Hotel::factory()->create([
            'operator_id' => $operator->id,
            'name_ar' => 'فندق دار التقوى',
            'name_en' => 'Dar Al Taqwa',
            'city' => HotelCity::Madinah,
        ]);

        $makkahQuad = RoomType::factory()->create(['hotel_id' => $makkahHotel->id, 'name' => 'Quad', 'capacity' => 4]);
        $madinahQuad = RoomType::factory()->create(['hotel_id' => $madinahHotel->id, 'name' => 'Quad', 'capacity' => 4]);

        $makkahContract = $this->contractWithRate($operator, $makkahHotel, $makkahQuad, purchase: 9000, sale: 16000);
        $this->contractWithRate($operator, $madinahHotel, $madinahQuad, purchase: 7000, sale: 13000);

        // A couple of tight nights so the hotel availability grid has something
        // to show for every state it can render, not just "available".
        $this->seedAvailabilityGridDemo($operator, $makkahHotel, $makkahQuad, $makkahContract);

        // The Makkah hotel has been reviewed and confirmed by ops staff; the Madinah
        // one is left unconfirmed to demonstrate the pending state in the UI.
        $opsAdmin = User::where('email', 'ops.admin@alrahma.test')->first();
        if ($opsAdmin) {
            $makkahHotel->confirm($opsAdmin);
        }

        // --- Geography catalog: cities, ports and a ziyarat site, replacing free-text
        //     from/to strings on movements and operating-order logistics. ---
        $jeddah = City::factory()->create([
            'operator_id' => $operator->id, 'name_ar' => 'جدة', 'name_en' => 'Jeddah',
            'is_port' => true, 'is_accommodation' => false, 'has_train' => true,
        ]);
        $makkahCity = City::factory()->create([
            'operator_id' => $operator->id, 'name_ar' => 'مكة المكرمة', 'name_en' => 'Makkah',
            'is_port' => false, 'is_accommodation' => true, 'has_train' => true,
        ]);
        $madinahCity = City::factory()->create([
            'operator_id' => $operator->id, 'name_ar' => 'المدينة المنورة', 'name_en' => 'Madinah',
            'is_port' => true, 'is_accommodation' => true, 'has_train' => true,
        ]);

        $jeddahAirport = Location::factory()->create([
            'operator_id' => $operator->id, 'city_id' => $jeddah->id, 'type' => LocationType::AirPort,
            'name_ar' => 'مطار الملك عبدالعزيز الدولي', 'name_en' => 'King Abdulaziz International Airport',
        ]);
        $madinahAirport = Location::factory()->create([
            'operator_id' => $operator->id, 'city_id' => $madinahCity->id, 'type' => LocationType::AirPort,
            'name_ar' => 'مطار الأمير محمد بن عبدالعزيز', 'name_en' => 'Prince Mohammad bin Abdulaziz Airport',
        ]);
        Location::factory()->create([
            'operator_id' => $operator->id, 'city_id' => $madinahCity->id, 'type' => LocationType::ZiyaratSite,
            'name_ar' => 'مسجد قباء', 'name_en' => 'Quba Mosque',
        ]);

        // --- Permit catalog: the operator-defined list of permit types it can issue. ---
        PermitType::factory()->create([
            'operator_id' => $operator->id, 'name_ar' => 'تصريح نسك للعمرة', 'name_en' => 'Nusuk Umrah Permit',
        ]);
        PermitType::factory()->create([
            'operator_id' => $operator->id, 'name_ar' => 'تصريح زيارة', 'name_en' => 'Ziyarat Permit',
        ]);

        // --- Movement type catalog: operator-defined labels tagged with a fixed
        //     category, replacing the old hardcoded MovementType enum. ---
        $movementTypeArrival = MovementType::factory()->create([
            'operator_id' => $operator->id, 'name_ar' => 'استقبال', 'name_en' => 'Arrival',
            'category' => MovementCategory::Arrival,
        ]);
        $movementTypeInternal = MovementType::factory()->create([
            'operator_id' => $operator->id, 'name_ar' => 'نقل داخلي', 'name_en' => 'Inter-city Transfer',
            'category' => MovementCategory::Internal,
        ]);
        $movementTypeDeparture = MovementType::factory()->create([
            'operator_id' => $operator->id, 'name_ar' => 'مغادرة', 'name_en' => 'Departure',
            'category' => MovementCategory::Departure,
        ]);

        // --- Group & pilgrims: a family (mahram-linked) plus two others. ---
        $group = Group::factory()->create([
            'operator_id' => $operator->id,
            'agent_id' => $agentSudan->id,
            'name' => 'Group SD-2026-014',
            'arrival_date' => now()->addDays(10),
            'departure_date' => now()->addDays(17),
        ]);

        $husband = Pilgrim::factory()->male()->create([
            'operator_id' => $operator->id, 'agent_id' => $agentSudan->id, 'group_id' => $group->id, 'name' => 'Ahmed Osman',
        ]);
        $wife = Pilgrim::factory()->female()->create([
            'operator_id' => $operator->id, 'agent_id' => $agentSudan->id, 'group_id' => $group->id, 'name' => 'Fatima Osman',
            'mahram_of_pilgrim_id' => $husband->id,
        ]);
        $otherMale = Pilgrim::factory()->male()->create([
            'operator_id' => $operator->id, 'agent_id' => $agentSudan->id, 'group_id' => $group->id, 'name' => 'Yusuf Ibrahim',
        ]);
        $otherFemale = Pilgrim::factory()->female()->create([
            'operator_id' => $operator->id, 'agent_id' => $agentSudan->id, 'group_id' => $group->id, 'name' => 'Aisha Suleiman',
        ]);
        $pilgrims = [$husband, $wife, $otherMale, $otherFemale];

        // --- The operating order: the document tying it all together. ---
        $order = OperatingOrder::factory()->create([
            'operator_id' => $operator->id,
            'agent_id' => $agentSudan->id,
            'group_id' => $group->id,
            'status' => OperatingOrderStatus::Approved,
            'approved_at' => now(),
            'adults_count' => count($pilgrims),
            'children_count' => 0,
            'infants_count' => 0,
            'arrival_date' => $group->arrival_date,
            'arrival_trip_mode' => TripMode::Air,
            'arrival_city_id' => $jeddah->id,
            'arrival_port_location_id' => $jeddahAirport->id,
            'arrival_carrier' => 'Saudia',
            'arrival_flight_no' => 'SV 799',
            'arrival_time' => '14:30',
            'departure_date' => $group->departure_date,
            'departure_trip_mode' => TripMode::Air,
            'departure_city_id' => $madinahCity->id,
            'departure_port_location_id' => $madinahAirport->id,
            'departure_carrier' => 'Saudia',
            'departure_flight_no' => 'SV 802',
            'departure_time' => '09:15',
        ]);

        // --- Movements: arrival, inter-city, departure — the manifest carries everyone. ---
        $contractedBus = Vehicle::where('transport_supplier_id', $transportSupplier->id)->first();

        $arrival = Movement::factory()->create([
            'operating_order_id' => $order->id, 'vehicle_id' => $ownBus->id,
            'movement_type_id' => $movementTypeArrival->id, 'source' => MovementSource::OperatorProvided,
            'status' => MovementStatus::Confirmed,
            'from_location' => 'Jeddah Airport', 'to_location' => 'Makkah Hotel',
            'movement_at' => $group->arrival_date, 'pilgrim_count' => count($pilgrims),
        ]);
        $interCity = Movement::factory()->create([
            'operating_order_id' => $order->id, 'vehicle_id' => $contractedBus?->id,
            'movement_type_id' => $movementTypeInternal->id, 'source' => MovementSource::OperatorProvided,
            'supplier_reference_no' => 'HRT-TR-2026-0092', 'status' => MovementStatus::Pending,
            'from_location' => 'Makkah Hotel', 'to_location' => 'Madinah Hotel',
            'movement_at' => $group->arrival_date->copy()->addDays(4), 'pilgrim_count' => count($pilgrims),
        ]);
        $departure = Movement::factory()->create([
            'operating_order_id' => $order->id, 'vehicle_id' => $ownBus->id,
            'movement_type_id' => $movementTypeDeparture->id, 'source' => MovementSource::OperatorProvided,
            'status' => MovementStatus::Pending,
            'from_location' => 'Madinah Hotel', 'to_location' => 'Madinah Airport',
            'movement_at' => $group->departure_date, 'pilgrim_count' => count($pilgrims),
        ]);
        foreach ([$arrival, $interCity, $departure] as $movement) {
            $movement->pilgrims()->attach(collect($pilgrims)->pluck('id'));
        }

        // --- Hotel stays: Makkah sold by the operator; Madinah brought by the agent
        //     itself — the operator sold no room there but still must know where
        //     everyone sleeps. See docs/PLAN.md §3. ---
        $makkahNights = 4;
        foreach ($pilgrims as $pilgrim) {
            $stay = HotelStay::factory()->create([
                'operating_order_id' => $order->id,
                'hotel_id' => $makkahHotel->id,
                'room_type_id' => $makkahQuad->id,
                'pilgrim_id' => $pilgrim->id,
                'check_in' => $group->arrival_date,
                'check_out' => $group->arrival_date->copy()->addDays($makkahNights),
                'status' => HotelStayStatus::Booked,
                'source' => HotelStaySource::OperatorSold,
            ]);

            OrderLine::create([
                'operating_order_id' => $order->id,
                'line_type' => OrderLineType::Hotel,
                'hotel_stay_id' => $stay->id,
                'description' => "Makkah — {$makkahQuad->name} bed — {$pilgrim->name}",
                'quantity' => $makkahNights,
                'purchase_price_cents' => 9000,
                'sale_price_cents' => 16000,
                'currency' => 'SAR',
            ]);
        }

        $madinahNights = 3;
        foreach ($pilgrims as $pilgrim) {
            HotelStay::factory()->create([
                'operating_order_id' => null, // sold by nobody here — the agent booked it directly
                'hotel_id' => $madinahHotel->id,
                'room_type_id' => $madinahQuad->id,
                'pilgrim_id' => $pilgrim->id,
                'check_in' => $group->arrival_date->copy()->addDays($makkahNights),
                'check_out' => $group->arrival_date->copy()->addDays($makkahNights + $madinahNights),
                'status' => HotelStayStatus::Booked,
                'source' => HotelStaySource::AgentProvided,
                'supplier_reference_no' => 'BRN-2026-7734',
            ]);
        }

        // --- Transport & visa lines. ---
        $transportLines = [
            [$arrival, 'Arrival transfer'],
            [$interCity, 'Makkah to Madinah'],
            [$departure, 'Departure transfer'],
        ];
        foreach ($transportLines as [$movement, $description]) {
            OrderLine::create([
                'operating_order_id' => $order->id,
                'line_type' => OrderLineType::Transport,
                'movement_id' => $movement->id,
                'description' => $description,
                'quantity' => 1,
                'purchase_price_cents' => 150000,
                'sale_price_cents' => 220000,
                'currency' => 'SAR',
            ]);
        }

        $visaService = Service::factory()->create([
            'operator_id' => $operator->id, 'type' => ServiceType::Visa, 'name' => 'Umrah Visa Processing',
        ]);
        OrderLine::create([
            'operating_order_id' => $order->id,
            'line_type' => OrderLineType::Service,
            'service_id' => $visaService->id,
            'description' => 'Umrah Visa — 4 pilgrims',
            'quantity' => count($pilgrims),
            'purchase_price_cents' => 30000,
            'sale_price_cents' => 45000,
            'currency' => 'SAR',
        ]);

        // --- Ledger: charge the agent the order total, record a partial payment. ---
        $order->refresh()->load('orderLines');
        LedgerEntry::create([
            'organization_id' => $operator->id,
            'counterparty_organization_id' => $agentSudan->id,
            'operating_order_id' => $order->id,
            'entry_type' => LedgerEntryType::Charge,
            'amount_cents' => $order->totalSaleCents(),
            'currency' => 'SAR',
            'description' => "Invoice for order {$order->reference_no}",
            'occurred_on' => now(),
        ]);
        LedgerEntry::create([
            'organization_id' => $operator->id,
            'counterparty_organization_id' => $agentSudan->id,
            'operating_order_id' => $order->id,
            'entry_type' => LedgerEntryType::Payment,
            'amount_cents' => intdiv($order->totalSaleCents(), 2),
            'currency' => 'SAR',
            'description' => 'Partial payment received',
            'occurred_on' => now(),
        ]);

        // A second, smaller order from the other agent, left in draft, so the
        // operator's dashboard and order list have more than one row to show.
        OperatingOrder::factory()->create([
            'operator_id' => $operator->id,
            'agent_id' => $agentTurkiye->id,
            'status' => OperatingOrderStatus::PendingReview,
        ]);

        // --- The agent's own sales: entirely its own business, nothing to do with
        //     the operator above. One fully paid both sides, one still owed by the
        //     customer, one refunded — so the Sales screen has something to show
        //     for every state, not just the happy path.
        $this->seedAgentSales($agentSudan);
    }

    /**
     * A ticket sold and fully settled both ways, a ticket the customer still owes
     * on, and a refunded one — the agent's own accounting, independent of any
     * operator relationship.
     */
    private function seedAgentSales(Organization $agent): void
    {
        $settled = AgentSale::create([
            'agent_organization_id' => $agent->id,
            'sale_type' => AgentSaleType::Ticket,
            'customer_name' => 'Ibrahim Adam',
            'description' => 'Khartoum – Jeddah – Khartoum',
            'supplier_reference' => 'PNR-7GXQ42',
            'sale_date' => now()->subDays(5),
            'purchase_price_cents' => 85000,
            'sale_price_cents' => 112000,
        ]);
        $settled->payments()->create(['direction' => AgentSalePaymentDirection::FromCustomer, 'amount_cents' => 112000, 'paid_on' => now()->subDays(5)]);
        $settled->payments()->create(['direction' => AgentSalePaymentDirection::ToSupplier, 'amount_cents' => 85000, 'paid_on' => now()->subDays(3)]);

        $outstanding = AgentSale::create([
            'agent_organization_id' => $agent->id,
            'sale_type' => AgentSaleType::Ticket,
            'customer_name' => 'Mona Khalid',
            'description' => 'Khartoum – Madinah',
            'supplier_reference' => 'PNR-4KLT19',
            'sale_date' => now()->subDay(),
            'purchase_price_cents' => 62000,
            'sale_price_cents' => 79000,
        ]);
        $outstanding->payments()->create(['direction' => AgentSalePaymentDirection::FromCustomer, 'amount_cents' => 30000, 'paid_on' => now()->subDay()]);

        $refunded = AgentSale::create([
            'agent_organization_id' => $agent->id,
            'sale_type' => AgentSaleType::Other,
            'customer_name' => 'Yusuf Ibrahim',
            'description' => 'Visa processing fee',
            'sale_date' => now()->subWeek(),
            'purchase_price_cents' => 15000,
            'sale_price_cents' => 25000,
            'status' => AgentSaleStatus::Refunded,
        ]);
        $refunded->payments()->create(['direction' => AgentSalePaymentDirection::FromCustomer, 'amount_cents' => 25000, 'paid_on' => now()->subWeek()]);
    }

    /**
     * Seed one user per role so the "who can do what" story is demonstrable
     * immediately: an operator admin/data-entry/supervisor/accountant, and an agent user.
     */
    private function addStaff(Organization $operator, Organization $agent): void
    {
        // The platform admin (config('app.admin_email')) is deliberately NOT added
        // here — they manage the roster of Umrah companies from their own admin
        // panel (pages::admin.umrah-companies.index), never as a member of one.
        $roles = [
            'ops.admin@alrahma.test' => ['name' => 'Nadia Al-Amin', 'role' => OrganizationRole::Admin],
            'ops.entry@alrahma.test' => ['name' => 'Bilal Hassan', 'role' => OrganizationRole::DataEntry],
            'ops.supervisor@alrahma.test' => ['name' => 'Khalid Mansour', 'role' => OrganizationRole::Supervisor],
            'ops.accountant@alrahma.test' => ['name' => 'Reem Qureshi', 'role' => OrganizationRole::Accountant],
        ];

        foreach ($roles as $email => $info) {
            $user = User::factory()->create(['name' => $info['name'], 'email' => $email]);
            $operator->addMember($user, $info['role']);
        }

        $agentUser = User::factory()->create(['name' => 'Mohammed Al-Sadiq', 'email' => 'agent@sudan-travel.test']);
        $agent->addMember($agentUser, OrganizationRole::AgentUser);
        $agentUser->forceFill(['current_organization_id' => $agent->id])->save();
    }

    /**
     * A contract for a single room type covering its own date range, with one
     * seasonal rate spanning the whole contract.
     */
    private function contractWithRate(Organization $operator, Hotel $hotel, RoomType $roomType, int $purchase, int $sale): HotelContract
    {
        $contract = HotelContract::factory()->create([
            'hotel_id' => $hotel->id,
            'operator_id' => $operator->id,
            'contract_type' => HotelContractType::Allotment,
            // Starts today (not in the future) so the availability grid's default
            // window — today through the next two weeks — has real data in it.
            'starts_on' => now()->startOfDay(),
            'ends_on' => now()->addDays(30),
            'release_days' => 14,
        ]);

        $contract->rates()->create([
            'room_type_id' => $roomType->id,
            'starts_on' => $contract->starts_on,
            'ends_on' => $contract->ends_on,
            'purchase_price_cents' => $purchase,
            'sale_price_cents' => $sale,
        ]);

        // 21 nights of ordinary allotment — generous enough that both the demo
        // family's real stay and the grid's default 14-night window land inside it.
        foreach (range(0, 20) as $offset) {
            $contract->allotments()->create([
                'room_type_id' => $roomType->id,
                'date' => $contract->starts_on->copy()->addDays($offset),
                'rooms_allotted' => 15,
            ]);
        }

        return $contract;
    }

    /**
     * The demo family alone never fills a 15-room allotment, so the availability
     * grid would only ever show green. Two extra nights here — one filled to
     * exactly capacity, one pushed past it — give it something to show for
     * "full" and "overbooked" too, using throwaway walk-in pilgrims that belong
     * to no group or order.
     */
    private function seedAvailabilityGridDemo(Organization $operator, Hotel $hotel, RoomType $roomType, HotelContract $contract): void
    {
        $fullNight = now()->startOfDay()->addDays(2);
        $overbookedNight = now()->startOfDay()->addDays(3);

        foreach ([$fullNight, $overbookedNight] as $night) {
            $existing = $contract->allotments()
                ->where('room_type_id', $roomType->id)
                ->whereDate('date', $night->format('Y-m-d'))
                ->first();

            if ($existing) {
                $existing->update(['rooms_allotted' => 2]);
            } else {
                $contract->allotments()->create([
                    'room_type_id' => $roomType->id,
                    'date' => $night,
                    'rooms_allotted' => 2,
                ]);
            }
        }

        $this->fillRoomTypeNight($operator, $hotel, $roomType, $fullNight, beds: $roomType->capacity * 2);
        $this->fillRoomTypeNight($operator, $hotel, $roomType, $overbookedNight, beds: $roomType->capacity * 3);
    }

    private function fillRoomTypeNight(Organization $operator, Hotel $hotel, RoomType $roomType, CarbonInterface $night, int $beds): void
    {
        for ($i = 0; $i < $beds; $i++) {
            $pilgrim = Pilgrim::factory()->create(['operator_id' => $operator->id]);

            HotelStay::factory()->create([
                'operating_order_id' => null,
                'hotel_id' => $hotel->id,
                'room_type_id' => $roomType->id,
                'pilgrim_id' => $pilgrim->id,
                'check_in' => $night,
                'check_out' => $night->copy()->addDay(),
                'status' => HotelStayStatus::Booked,
                'source' => HotelStaySource::OperatorSold,
            ]);
        }
    }
}
