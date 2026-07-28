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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
