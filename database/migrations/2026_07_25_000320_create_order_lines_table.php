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
CREATE TABLE "order_lines" ("id" integer primary key autoincrement not null, "operating_order_id" integer not null, "line_type" varchar not null, "hotel_stay_id" integer, "movement_id" integer, "service_id" integer, "description" varchar, "quantity" integer not null default '1', "purchase_price_cents" integer not null default '0', "sale_price_cents" integer not null default '0', "currency" varchar not null default 'SAR', "created_at" datetime, "updated_at" datetime, foreign key("operating_order_id") references "operating_orders"("id") on delete cascade, foreign key("hotel_stay_id") references "hotel_stays"("id") on delete set null, foreign key("movement_id") references "movements"("id") on delete set null, foreign key("service_id") references "services"("id") on delete set null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "order_lines_operating_order_id_line_type_index" on "order_lines" ("operating_order_id", "line_type");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "order_lines_hotel_stay_id_index" on "order_lines" ("hotel_stay_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "order_lines_movement_id_index" on "order_lines" ("movement_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "order_lines_service_id_index" on "order_lines" ("service_id");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
