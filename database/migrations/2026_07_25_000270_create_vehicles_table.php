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
    }
};
