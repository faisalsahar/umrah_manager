<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Operator-defined catalogs — the pattern used throughout instead of fixed PHP
     * enums, so a new vehicle type, movement type, permit type, or service is just
     * a row, not a migration. `cities`/`locations` replace free-text place names
     * with a real geography catalog (one polymorphic `locations` table with a
     * `type` column, rather than a table per kind of place).
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
CREATE TABLE "cities" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "name_ar" varchar not null, "name_en" varchar not null, "is_port" tinyint(1) not null default '0', "is_accommodation" tinyint(1) not null default '0', "has_train" tinyint(1) not null default '0', "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "cities_operator_id_index" on "cities" ("operator_id");
SQL
        );

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

        DB::unprepared(<<<'SQL'
CREATE TABLE "permit_types" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "name_ar" varchar not null, "name_en" varchar not null, "notes" text, "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "permit_types_operator_id_index" on "permit_types" ("operator_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "movement_types" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "name_ar" varchar not null, "name_en" varchar not null, "category" varchar not null, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "movement_types_operator_id_category_index" on "movement_types" ("operator_id", "category");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "vehicle_types" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "name" varchar not null, "capacity" integer not null, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "vehicle_types_operator_id_name_unique" on "vehicle_types" ("operator_id", "name");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "services" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "type" varchar not null, "name" varchar not null, "default_sale_price_cents" integer, "currency" varchar not null default 'SAR', "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "services_operator_id_type_index" on "services" ("operator_id", "type");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
        Schema::dropIfExists('vehicle_types');
        Schema::dropIfExists('movement_types');
        Schema::dropIfExists('permit_types');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('cities');
    }
};
