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
CREATE TABLE "failed_jobs" ("id" integer primary key autoincrement not null, "uuid" varchar not null, "connection" varchar not null, "queue" varchar not null, "payload" text not null, "exception" text not null, "failed_at" datetime not null default CURRENT_TIMESTAMP);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "failed_jobs_connection_queue_failed_at_index" on "failed_jobs" ("connection", "queue", "failed_at");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs" ("uuid");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
    }
};
