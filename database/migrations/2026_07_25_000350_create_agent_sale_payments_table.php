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
CREATE TABLE "agent_sale_payments" ("id" integer primary key autoincrement not null, "agent_sale_id" integer not null, "direction" varchar not null, "amount_cents" integer not null, "paid_on" date not null, "note" varchar, "created_at" datetime, "updated_at" datetime, foreign key("agent_sale_id") references "agent_sales"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "agent_sale_payments_agent_sale_id_direction_index" on "agent_sale_payments" ("agent_sale_id", "direction");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_sale_payments');
    }
};
