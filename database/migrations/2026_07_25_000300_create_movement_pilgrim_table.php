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
CREATE TABLE "movement_pilgrim" ("id" integer primary key autoincrement not null, "movement_id" integer not null, "pilgrim_id" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("movement_id") references "movements"("id") on delete cascade, foreign key("pilgrim_id") references "pilgrims"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "movement_pilgrim_movement_id_pilgrim_id_unique" on "movement_pilgrim" ("movement_id", "pilgrim_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "movement_pilgrim_pilgrim_id_index" on "movement_pilgrim" ("pilgrim_id");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movement_pilgrim');
    }
};
