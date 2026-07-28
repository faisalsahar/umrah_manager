<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operating_orders');
    }
};
