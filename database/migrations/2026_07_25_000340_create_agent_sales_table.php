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
CREATE TABLE "agent_sales" ("id" integer primary key autoincrement not null, "reference_no" varchar not null, "agent_organization_id" integer not null, "recorded_by_user_id" integer, "sale_type" varchar not null, "status" varchar not null default 'issued', "customer_name" varchar not null, "description" varchar, "supplier_reference" varchar, "sale_date" date not null, "purchase_price_cents" integer not null default '0', "sale_price_cents" integer not null default '0', "currency" varchar not null default 'SAR', "created_at" datetime, "updated_at" datetime, foreign key("agent_organization_id") references "organizations"("id") on delete cascade, foreign key("recorded_by_user_id") references "users"("id") on delete set null);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "agent_sales_reference_no_unique" on "agent_sales" ("reference_no");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "agent_sales_agent_organization_id_index" on "agent_sales" ("agent_organization_id");
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE INDEX "agent_sales_sale_date_index" on "agent_sales" ("sale_date");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_sales');
    }
};
