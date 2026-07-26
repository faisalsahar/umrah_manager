<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The operating order (أمر التشغيل) ties an operator, an agent, a group, and —
     * through order lines — hotels and transport together; everything else hangs
     * off it. `movements` and `hotel_stays` can each exist without a sold order
     * line, which is how an agent's own transport/hotel booking still gets
     * tracked. See docs/PLAN.md §3.
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
CREATE TABLE "operating_orders" ("id" integer primary key autoincrement not null, "reference_no" varchar not null, "operator_id" integer not null, "agent_id" integer, "group_id" integer, "entered_by_user_id" integer, "status" varchar not null default ('draft'), "arrival_date" date, "travel_mode" varchar, "notes" text, "approved_at" datetime, "created_at" datetime, "updated_at" datetime, "adults_count" integer, "children_count" integer, "infants_count" integer, "arrival_trip_mode" varchar, "arrival_city_id" integer, "arrival_port_location_id" integer, "arrival_carrier" varchar, "arrival_flight_no" varchar, "arrival_time" time, "departure_trip_mode" varchar, "departure_city_id" integer, "departure_port_location_id" integer, "departure_carrier" varchar, "departure_flight_no" varchar, "departure_date" date, "departure_time" time, foreign key("entered_by_user_id") references users("id") on delete set null on update no action, foreign key("group_id") references groups("id") on delete set null on update no action, foreign key("agent_id") references organizations("id") on delete set null on update no action, foreign key("operator_id") references organizations("id") on delete cascade on update no action, foreign key("arrival_city_id") references "cities"("id") on delete set null, foreign key("arrival_port_location_id") references "locations"("id") on delete set null, foreign key("departure_city_id") references "cities"("id") on delete set null, foreign key("departure_port_location_id") references "locations"("id") on delete set null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "operating_orders_agent_id_status_index" on "operating_orders" ("agent_id", "status");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "operating_orders_entered_by_user_id_index" on "operating_orders" ("entered_by_user_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "operating_orders_group_id_index" on "operating_orders" ("group_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "operating_orders_operator_id_status_index" on "operating_orders" ("operator_id", "status");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "operating_orders_reference_no_unique" on "operating_orders" ("reference_no");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "operating_orders_arrival_city_id_index" on "operating_orders" ("arrival_city_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "operating_orders_arrival_port_location_id_index" on "operating_orders" ("arrival_port_location_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "operating_orders_departure_city_id_index" on "operating_orders" ("departure_city_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "operating_orders_departure_port_location_id_index" on "operating_orders" ("departure_port_location_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "movements" ("id" integer primary key autoincrement not null, "operating_order_id" integer not null, "vehicle_id" integer, "transport_supplier_id" integer, "status" varchar not null default ('pending'), "from_location" varchar, "to_location" varchar, "movement_at" datetime, "pilgrim_count" integer not null default ('0'), "supervisor_name" varchar, "notes" text, "created_at" datetime, "updated_at" datetime, "movement_type_id" integer not null, "source" varchar not null default 'operator_provided', "supplier_reference_no" varchar, "stopover_location_id" integer, foreign key("transport_supplier_id") references organizations("id") on delete set null on update no action, foreign key("vehicle_id") references vehicles("id") on delete set null on update no action, foreign key("operating_order_id") references operating_orders("id") on delete cascade on update no action, foreign key("movement_type_id") references "movement_types"("id") on delete restrict, foreign key("stopover_location_id") references "locations"("id") on delete set null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "movements_movement_at_index" on "movements" ("movement_at");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "movements_transport_supplier_id_index" on "movements" ("transport_supplier_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "movements_vehicle_id_index" on "movements" ("vehicle_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "movements_operating_order_id_movement_type_id_index" on "movements" ("operating_order_id", "movement_type_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "movements_stopover_location_id_index" on "movements" ("stopover_location_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "movement_pilgrim" ("id" integer primary key autoincrement not null, "movement_id" integer not null, "pilgrim_id" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("movement_id") references "movements"("id") on delete cascade, foreign key("pilgrim_id") references "pilgrims"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "movement_pilgrim_movement_id_pilgrim_id_unique" on "movement_pilgrim" ("movement_id", "pilgrim_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "movement_pilgrim_pilgrim_id_index" on "movement_pilgrim" ("pilgrim_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "hotel_stays" ("id" integer primary key autoincrement not null, "operating_order_id" integer, "hotel_id" integer not null, "room_type_id" integer not null, "pilgrim_id" integer not null, "gender" varchar not null, "room_no" varchar, "check_in" date not null, "check_out" date not null, "status" varchar not null default 'booked', "source" varchar not null, "created_at" datetime, "updated_at" datetime, "supplier_reference_no" varchar, foreign key("operating_order_id") references "operating_orders"("id") on delete set null, foreign key("hotel_id") references "hotels"("id") on delete cascade, foreign key("room_type_id") references "room_types"("id") on delete cascade, foreign key("pilgrim_id") references "pilgrims"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "hotel_stays_hotel_id_check_in_check_out_index" on "hotel_stays" ("hotel_id", "check_in", "check_out");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "hotel_stays_pilgrim_id_index" on "hotel_stays" ("pilgrim_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "hotel_stays_room_type_id_index" on "hotel_stays" ("room_type_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "hotel_stays_operating_order_id_index" on "hotel_stays" ("operating_order_id");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_stays');
        Schema::dropIfExists('movement_pilgrim');
        Schema::dropIfExists('movements');
        Schema::dropIfExists('operating_orders');
    }
};
