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
CREATE TABLE "locations" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "city_id" integer, "type" varchar not null, "name_ar" varchar not null, "name_en" varchar not null, "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade, foreign key("city_id") references "cities"("id") on delete set null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "locations_operator_id_type_index" on "locations" ("operator_id", "type");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "locations_city_id_index" on "locations" ("city_id");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
