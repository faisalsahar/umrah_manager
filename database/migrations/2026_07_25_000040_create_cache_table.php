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
CREATE TABLE "cache" ("key" varchar not null, "value" text not null, "expiration" integer not null, primary key ("key"));
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "cache_expiration_index" on "cache" ("expiration");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
    }
};
