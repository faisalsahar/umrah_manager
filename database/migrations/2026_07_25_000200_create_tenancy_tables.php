<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The tenancy model this whole app is built on: every party that transacts
     * (operator, agent, hotel, transport supplier, platform) is an `organizations`
     * row, and a user's powers come entirely from their membership in one — never
     * from a column on the user itself. See docs/PLAN.md §1-2.
     *
     * Consolidated from what was previously a dozen-plus incremental migration
     * files into one per domain area — the exact schema is unchanged (this is
     * a straight dump of the tables/indexes as they existed), only the file
     * layout is simplified. See docs/PLAN.md for the design reasoning behind
     * each table, which lived across the original files this replaces.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE "organizations" ("id" integer primary key autoincrement not null, "type" varchar not null, "country" varchar, "license_no" varchar, "is_active" tinyint(1) not null default ('1'), "created_at" datetime, "updated_at" datetime, "name_ar" varchar not null, "name_en" varchar not null, "parent_organization_id" integer, "subscription_expires_at" date, "external_code" varchar, foreign key("parent_organization_id") references "organizations"("id") on delete set null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "organizations_type_index" on "organizations" ("type");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "organizations_parent_organization_id_index" on "organizations" ("parent_organization_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "organizations_external_code_index" on "organizations" ("external_code");
SQL
        );

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
        Schema::dropIfExists('organizations');
    }
};
