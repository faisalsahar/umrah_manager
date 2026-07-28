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
CREATE TABLE "services" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "type" varchar not null, "name" varchar not null, "default_sale_price_cents" integer, "currency" varchar not null default 'SAR', "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "services_operator_id_type_index" on "services" ("operator_id", "type");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
