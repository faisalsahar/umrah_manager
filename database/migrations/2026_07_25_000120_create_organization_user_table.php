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
CREATE TABLE "organization_user" ("id" integer primary key autoincrement not null, "organization_id" integer not null, "user_id" integer not null, "role" varchar not null, "created_at" datetime, "updated_at" datetime, foreign key("organization_id") references "organizations"("id") on delete cascade, foreign key("user_id") references "users"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "organization_user_organization_id_user_id_unique" on "organization_user" ("organization_id", "user_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "organization_user_user_id_index" on "organization_user" ("user_id");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};
