<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Money: order lines carry purchase/sale price in minor currency units, and a
     * ledger computes an organization's balance with a counterparty by summing
     * entries — never a stored running total, so it can never drift out of sync.
     * See docs/PLAN.md §7.
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

        DB::unprepared(<<<'SQL'
CREATE TABLE "ledger_entries" ("id" integer primary key autoincrement not null, "organization_id" integer not null, "counterparty_organization_id" integer not null, "operating_order_id" integer, "entry_type" varchar not null, "amount_cents" integer not null, "currency" varchar not null default 'SAR', "description" varchar, "occurred_on" date not null, "created_at" datetime, "updated_at" datetime, foreign key("organization_id") references "organizations"("id") on delete cascade, foreign key("counterparty_organization_id") references "organizations"("id") on delete cascade, foreign key("operating_order_id") references "operating_orders"("id") on delete set null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "ledger_entries_organization_id_counterparty_organization_id_index" on "ledger_entries" ("organization_id", "counterparty_organization_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "ledger_entries_occurred_on_index" on "ledger_entries" ("occurred_on");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "ledger_entries_counterparty_organization_id_index" on "ledger_entries" ("counterparty_organization_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "ledger_entries_operating_order_id_index" on "ledger_entries" ("operating_order_id");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('order_lines');
    }
};
