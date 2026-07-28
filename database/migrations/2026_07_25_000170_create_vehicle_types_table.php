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
CREATE TABLE "vehicle_types" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "name" varchar not null, "capacity" integer not null, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "vehicle_types_operator_id_name_unique" on "vehicle_types" ("operator_id", "name");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};
