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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pilgrims');
    }
};
