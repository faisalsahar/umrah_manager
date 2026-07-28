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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
