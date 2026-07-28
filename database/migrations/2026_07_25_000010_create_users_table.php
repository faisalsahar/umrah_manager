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
CREATE TABLE "users" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar not null, "email_verified_at" datetime, "password" varchar, "remember_token" varchar, "created_at" datetime, "updated_at" datetime, "two_factor_secret" text, "two_factor_recovery_codes" text, "two_factor_confirmed_at" datetime, "avatar" varchar, "provider" varchar, "provider_id" varchar, "onboarding_code" varchar, "onboarding_notified_at" datetime, "current_organization_id" integer, "locale" varchar, foreign key("current_organization_id") references "organizations"("id") on delete set null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "users_email_unique" on "users" ("email");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "users_onboarding_code_unique" on "users" ("onboarding_code");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "users_current_organization_id_index" on "users" ("current_organization_id");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
