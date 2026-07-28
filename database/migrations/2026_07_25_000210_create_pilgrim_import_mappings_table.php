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
CREATE TABLE "pilgrim_import_mappings" ("id" integer primary key autoincrement not null, "operator_id" integer not null, "headers_signature" varchar not null, "mapping" text not null, "created_at" datetime, "updated_at" datetime, foreign key("operator_id") references "organizations"("id") on delete cascade);
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX "pilgrim_import_mappings_operator_id_headers_signature_unique" on "pilgrim_import_mappings" ("operator_id", "headers_signature");
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pilgrim_import_mappings');
    }
};
