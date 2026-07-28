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
CREATE TABLE "cities" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "name_ar" varchar not null, "name_en" varchar not null, "is_port" tinyint(1) not null default '0', "is_accommodation" tinyint(1) not null default '0', "has_train" tinyint(1) not null default '0', "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "cities_operator_id_index" on "cities" ("operator_id");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
