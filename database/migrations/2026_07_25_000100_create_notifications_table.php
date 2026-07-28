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
CREATE TABLE "notifications" ("id" varchar not null, "type" varchar not null, "notifiable_type" varchar not null, "notifiable_id" integer not null, "data" text not null, "read_at" datetime, "created_at" datetime, "updated_at" datetime, primary key ("id"));
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "notifications_notifiable_type_notifiable_id_index" on "notifications" ("notifiable_type", "notifiable_id");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
