<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Laravel/package foundation tables: users (carrying onboarding codes, locale,
     * OAuth provider identity, 2FA, and the current-organization pointer used
     * throughout the app — see User::currentOrganization()), the framework's
     * cache/queue/session tables, WebAuthn passkeys, and notifications.
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

        DB::unprepared(<<<'SQL'
CREATE TABLE "password_reset_tokens" ("email" varchar not null, "token" varchar not null, "created_at" datetime, primary key ("email"));
SQL
        );

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

        DB::unprepared(<<<'SQL'
CREATE TABLE "cache" ("key" varchar not null, "value" text not null, "expiration" integer not null, primary key ("key"));
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "cache_expiration_index" on "cache" ("expiration");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "cache_locks" ("key" varchar not null, "owner" varchar not null, "expiration" integer not null, primary key ("key"));
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "cache_locks_expiration_index" on "cache_locks" ("expiration");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "jobs" ("id" integer primary key autoincrement not null, "queue" varchar not null, "payload" text not null, "attempts" integer not null, "reserved_at" integer, "available_at" integer not null, "created_at" integer not null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "jobs_queue_index" on "jobs" ("queue");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TABLE "job_batches" ("id" varchar not null, "name" varchar not null, "total_jobs" integer not null, "pending_jobs" integer not null, "failed_jobs" integer not null, "failed_job_ids" text not null, "options" text, "cancelled_at" integer, "created_at" integer not null, "finished_at" integer, primary key ("id"));
SQL
        );

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

        DB::unprepared(<<<'SQL'
CREATE TABLE "passkeys" ("id" integer primary key autoincrement not null, "user_id" integer not null, "name" varchar not null, "credential_id" varchar not null, "credential" text not null, "last_used_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "passkeys_user_id_index" on "passkeys" ("user_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "passkeys_credential_id_unique" on "passkeys" ("credential_id");
SQL
        );

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
        Schema::dropIfExists('passkeys');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
