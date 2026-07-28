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
CREATE TABLE "room_types" ("id" integer primary key autoincrement not null, "hotel_id" integer not null, "name" varchar not null, "capacity" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("hotel_id") references "hotels"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "room_types_hotel_id_name_unique" on "room_types" ("hotel_id", "name");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
