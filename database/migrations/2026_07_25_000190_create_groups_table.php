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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
