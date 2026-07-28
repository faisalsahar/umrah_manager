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
    }
};
