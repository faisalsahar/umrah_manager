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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_allotments');
    }
};
