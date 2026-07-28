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
    }
};
