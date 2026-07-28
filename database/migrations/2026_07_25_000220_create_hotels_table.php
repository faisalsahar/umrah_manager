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
CREATE TABLE "hotels" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "managed_by_organization_id" integer, "city" varchar not null, "stars" integer, "is_active" tinyint(1) not null default ('1'), "created_at" datetime, "updated_at" datetime, "name_ar" varchar not null, "name_en" varchar not null, "confirmed_at" datetime, "confirmed_by_user_id" integer, "deleted_at" datetime, foreign key("operator_id") references organizations("id") on delete cascade on update no action, foreign key("managed_by_organization_id") references organizations("id") on delete set null on update no action, foreign key("confirmed_by_user_id") references "users"("id") on delete set null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "hotels_managed_by_organization_id_index" on "hotels" ("managed_by_organization_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "hotels_operator_id_city_index" on "hotels" ("operator_id", "city");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
