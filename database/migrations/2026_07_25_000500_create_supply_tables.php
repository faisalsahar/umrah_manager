<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Hotels and vehicles: the two things an operator contracts or owns to actually
     * run a trip. Hotels sell by the BED (room_types.capacity), not the room —
     * see docs/PLAN.md §4. Hotel archival is a soft delete (hotels.deleted_at),
     * not a hard one.
     *
     * Consolidated from what was previously a dozen-plus incremental migration
     * files into one per domain area — the exact schema is unchanged (this is
     * a straight dump of the tables/indexes as they existed), only the file
     * layout is simplified. See docs/PLAN.md for the design reasoning behind
     * each table, which lived across the original files this replaces.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE "hotels" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "managed_by_organization_id" integer, "city" varchar not null, "stars" integer, "is_active" tinyint(1) not null default ('1'), "created_at" datetime, "updated_at" datetime, "name_ar" varchar not null, "name_en" varchar not null, "confirmed_at" datetime, "confirmed_by_user_id" integer, "deleted_at" datetime, foreign key("operator_id") references organizations("id") on delete cascade on update no action, foreign key("managed_by_organization_id") references organizations("id") on delete set null on update no action, foreign key("confirmed_by_user_id") references "users"("id") on delete set null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "hotels_managed_by_organization_id_index" on "hotels" ("managed_by_organization_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "hotels_operator_id_city_index" on "hotels" ("operator_id", "city");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "room_types" ("id" integer primary key autoincrement not null, "hotel_id" integer not null, "name" varchar not null, "capacity" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("hotel_id") references "hotels"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "room_types_hotel_id_name_unique" on "room_types" ("hotel_id", "name");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "hotel_contracts" ("id" integer primary key autoincrement not null, "hotel_id" integer not null, "operator_id" integer not null, "contract_type" varchar not null, "starts_on" date not null, "ends_on" date not null, "release_days" integer, "currency" varchar not null default 'SAR', "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("hotel_id") references "hotels"("id") on delete cascade, foreign key("operator_id") references "organizations"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "hotel_contracts_hotel_id_starts_on_ends_on_index" on "hotel_contracts" ("hotel_id", "starts_on", "ends_on");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "hotel_contracts_operator_id_index" on "hotel_contracts" ("operator_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "contract_rates" ("id" integer primary key autoincrement not null, "hotel_contract_id" integer not null, "room_type_id" integer not null, "starts_on" date not null, "ends_on" date not null, "purchase_price_cents" integer not null, "sale_price_cents" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("hotel_contract_id") references "hotel_contracts"("id") on delete cascade, foreign key("room_type_id") references "room_types"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "contract_rates_room_type_id_starts_on_ends_on_index" on "contract_rates" ("room_type_id", "starts_on", "ends_on");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "contract_rates_hotel_contract_id_index" on "contract_rates" ("hotel_contract_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "contract_allotments" ("id" integer primary key autoincrement not null, "hotel_contract_id" integer not null, "room_type_id" integer not null, "date" date not null, "rooms_allotted" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("hotel_contract_id") references "hotel_contracts"("id") on delete cascade, foreign key("room_type_id") references "room_types"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "contract_allotments_unique_night" on "contract_allotments" ("hotel_contract_id", "room_type_id", "date");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "contract_allotments_room_type_id_index" on "contract_allotments" ("room_type_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "vehicles" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "vehicle_type_id" integer not null, "transport_supplier_id" integer, "plate_no" varchar, "driver_name" varchar, "driver_phone" varchar, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade, foreign key("vehicle_type_id") references "vehicle_types"("id") on delete cascade, foreign key("transport_supplier_id") references "organizations"("id") on delete set null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "vehicles_operator_id_index" on "vehicles" ("operator_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "vehicles_vehicle_type_id_index" on "vehicles" ("vehicle_type_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "vehicles_transport_supplier_id_index" on "vehicles" ("transport_supplier_id");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('contract_allotments');
        Schema::dropIfExists('contract_rates');
        Schema::dropIfExists('hotel_contracts');
        Schema::dropIfExists('room_types');
        Schema::dropIfExists('hotels');
    }
};
