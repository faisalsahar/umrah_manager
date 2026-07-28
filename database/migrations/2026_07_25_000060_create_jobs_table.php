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
CREATE TABLE "jobs" ("id" integer primary key autoincrement not null, "queue" varchar not null, "payload" text not null, "attempts" integer not null, "reserved_at" integer, "available_at" integer not null, "created_at" integer not null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "jobs_queue_index" on "jobs" ("queue");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
