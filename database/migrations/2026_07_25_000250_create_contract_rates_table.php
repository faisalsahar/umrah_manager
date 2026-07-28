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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_rates');
    }
};
