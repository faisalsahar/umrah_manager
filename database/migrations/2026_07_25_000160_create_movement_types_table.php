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
CREATE TABLE "movement_types" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "name_ar" varchar not null, "name_en" varchar not null, "category" varchar not null, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "movement_types_operator_id_category_index" on "movement_types" ("operator_id", "category");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movement_types');
    }
};
