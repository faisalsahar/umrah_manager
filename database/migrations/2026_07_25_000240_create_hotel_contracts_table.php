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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_contracts');
    }
};
