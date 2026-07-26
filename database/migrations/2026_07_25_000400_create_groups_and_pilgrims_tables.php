<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pilgrims are records, not users — they never log in, and are matched on
     * passport number rather than name. `pilgrim_import_mappings` remembers a
     * previously-used Excel column mapping per operator so a repeat upload from
     * the same source system skips straight to review. See docs/PLAN.md §2.
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
CREATE TABLE "groups" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "agent_id" integer, "name" varchar not null, "visa_type" varchar, "arrival_date" date, "departure_date" date, "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade, foreign key("agent_id") references "organizations"("id") on delete set null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "groups_operator_id_arrival_date_index" on "groups" ("operator_id", "arrival_date");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "groups_agent_id_index" on "groups" ("agent_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "pilgrims" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "agent_id" integer, "group_id" integer, "name" varchar not null, "passport_number" varchar not null, "nationality" varchar, "gender" varchar, "date_of_birth" date, "phone" varchar, "mahram_of_pilgrim_id" integer, "created_at" datetime, "updated_at" datetime, "visa_status" varchar, "visa_number" varchar, "biometric_status" varchar, "mofa_number" varchar, "booking_type" varchar, foreign key("operator_id") references "organizations"("id") on delete cascade, foreign key("agent_id") references "organizations"("id") on delete set null, foreign key("group_id") references "groups"("id") on delete set null, foreign key("mahram_of_pilgrim_id") references "pilgrims"("id") on delete set null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "pilgrims_operator_id_passport_number_index" on "pilgrims" ("operator_id", "passport_number");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "pilgrims_agent_id_index" on "pilgrims" ("agent_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "pilgrims_group_id_index" on "pilgrims" ("group_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "pilgrims_mahram_of_pilgrim_id_index" on "pilgrims" ("mahram_of_pilgrim_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "pilgrim_import_mappings" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "headers_signature" varchar not null, "mapping" text not null, "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "pilgrim_import_mappings_operator_id_headers_signature_unique" on "pilgrim_import_mappings" ("operator_id", "headers_signature");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pilgrim_import_mappings');
        Schema::dropIfExists('pilgrims');
        Schema::dropIfExists('groups');
    }
};
