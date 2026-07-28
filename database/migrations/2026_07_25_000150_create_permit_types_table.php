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
CREATE TABLE "permit_types" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "name_ar" varchar not null, "name_en" varchar not null, "notes" text, "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "permit_types_operator_id_index" on "permit_types" ("operator_id");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permit_types');
    }
};
