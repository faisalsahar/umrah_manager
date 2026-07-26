<?php

namespace App\Support;

use App\Enums\HotelCity;
use App\Enums\LocationType;
use App\Enums\MovementCategory;
use App\Enums\ServiceType;
use App\Models\City;
use App\Models\Hotel;
use App\Models\Location;
use App\Models\MovementType;
use App\Models\Organization;
use App\Models\PermitType;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\VehicleType;

/**
 * The starter catalog a brand-new Umrah company gets so it isn't staring at empty
 * screens on day one — vehicle types, common cities/ports, a movement-type and
 * permit-type catalog, a couple of general services, and two starter hotels with
 * one room type each. All of it is just a sensible starting point: the company is
 * expected to rename, extend, or deactivate any of it once it has real contracts.
 */
class OperatorDefaults
{
    public static function seed(Organization $operator): void
    {
        self::seedVehicleTypes($operator);
        [$makkah, $madinah, $jeddah] = self::seedCities($operator);
        self::seedLocations($operator, $madinah, $jeddah);
        self::seedMovementTypes($operator);
        self::seedPermitTypes($operator);
        self::seedServices($operator);
        self::seedHotels($operator);
    }

    private static function seedVehicleTypes(Organization $operator): void
    {
        foreach ([
            ['name' => 'Sedan', 'capacity' => 4],
            ['name' => 'Hiace / Van', 'capacity' => 11],
            ['name' => 'Bus - 30 Seater', 'capacity' => 30],
            ['name' => 'Bus - 49 Seater', 'capacity' => 49],
        ] as $type) {
            VehicleType::create(['operator_id' => $operator->id, ...$type]);
        }
    }

    /**
     * @return array{City, City, City} [makkah, madinah, jeddah]
     */
    private static function seedCities(Organization $operator): array
    {
        $makkah = City::create([
            'operator_id' => $operator->id, 'name_ar' => 'مكة المكرمة', 'name_en' => 'Makkah',
            'is_port' => false, 'is_accommodation' => true, 'has_train' => true,
        ]);
        $madinah = City::create([
            'operator_id' => $operator->id, 'name_ar' => 'المدينة المنورة', 'name_en' => 'Madinah',
            'is_port' => true, 'is_accommodation' => true, 'has_train' => true,
        ]);
        $jeddah = City::create([
            'operator_id' => $operator->id, 'name_ar' => 'جدة', 'name_en' => 'Jeddah',
            'is_port' => true, 'is_accommodation' => false, 'has_train' => true,
        ]);

        return [$makkah, $madinah, $jeddah];
    }

    private static function seedLocations(Organization $operator, City $madinah, City $jeddah): void
    {
        Location::create([
            'operator_id' => $operator->id, 'city_id' => $jeddah->id, 'type' => LocationType::AirPort,
            'name_ar' => 'مطار الملك عبدالعزيز الدولي', 'name_en' => 'King Abdulaziz International Airport',
        ]);
        Location::create([
            'operator_id' => $operator->id, 'city_id' => $madinah->id, 'type' => LocationType::AirPort,
            'name_ar' => 'مطار الأمير محمد بن عبدالعزيز', 'name_en' => 'Prince Mohammad bin Abdulaziz Airport',
        ]);
    }

    private static function seedMovementTypes(Organization $operator): void
    {
        foreach ([
            ['category' => MovementCategory::Arrival, 'name_ar' => 'استقبال', 'name_en' => 'Arrival'],
            ['category' => MovementCategory::Internal, 'name_ar' => 'نقل داخلي', 'name_en' => 'Inter-city Transfer'],
            ['category' => MovementCategory::Ziyarat, 'name_ar' => 'زيارة', 'name_en' => 'Ziyarat Tour'],
            ['category' => MovementCategory::Departure, 'name_ar' => 'مغادرة', 'name_en' => 'Departure'],
        ] as $type) {
            MovementType::create(['operator_id' => $operator->id, ...$type]);
        }
    }

    private static function seedPermitTypes(Organization $operator): void
    {
        PermitType::create(['operator_id' => $operator->id, 'name_ar' => 'تصريح نسك للعمرة', 'name_en' => 'Nusuk Umrah Permit']);
        PermitType::create(['operator_id' => $operator->id, 'name_ar' => 'تصريح زيارة', 'name_en' => 'Ziyarat Permit']);
    }

    private static function seedServices(Organization $operator): void
    {
        foreach ([
            ['type' => ServiceType::Visa, 'name' => 'Umrah Visa Processing'],
            ['type' => ServiceType::Ziyarat, 'name' => 'Ziyarat Tour'],
            ['type' => ServiceType::Catering, 'name' => 'Catering'],
        ] as $service) {
            Service::create(['operator_id' => $operator->id, ...$service]);
        }
    }

    /**
     * Two starter hotels (one per holy city), each with a single Quad room type —
     * a template to rename and extend once the company has real contracts, not a
     * claim that it actually holds these properties.
     */
    private static function seedHotels(Organization $operator): void
    {
        $makkahHotel = Hotel::create([
            'operator_id' => $operator->id, 'city' => HotelCity::Makkah,
            'name_ar' => 'فندق مكة (عدّل هذا الاسم)', 'name_en' => 'Makkah Hotel (rename me)',
        ]);
        RoomType::create(['hotel_id' => $makkahHotel->id, 'name' => 'Quad', 'capacity' => 4]);

        $madinahHotel = Hotel::create([
            'operator_id' => $operator->id, 'city' => HotelCity::Madinah,
            'name_ar' => 'فندق المدينة (عدّل هذا الاسم)', 'name_en' => 'Madinah Hotel (rename me)',
        ]);
        RoomType::create(['hotel_id' => $madinahHotel->id, 'name' => 'Quad', 'capacity' => 4]);
    }
}
