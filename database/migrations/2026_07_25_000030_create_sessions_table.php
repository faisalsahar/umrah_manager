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
CREATE TABLE "sessions" ("id" varchar not null, "user_id" integer, "ip_address" varchar, "user_agent" text, "payload" text not null, "last_activity" integer not null, primary key ("id"));
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "sessions_user_id_index" on "sessions" ("user_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "sessions_last_activity_index" on "sessions" ("last_activity");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
