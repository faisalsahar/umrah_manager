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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movements');
    }
};
