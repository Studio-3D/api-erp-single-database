<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            // Add '10' to the enum set for statut in base DB
            DB::statement("ALTER TABLE statut_prospects MODIFY statut ENUM('0','1','2','3','4','5','6','7','8','9','10') NOT NULL");
        } catch (\Throwable $e) {
            // ignore if table/column not found
        }
    }

    public function down(): void
    {
        try {
            // Remove '10' from the enum set (may fail if rows have '10')
            DB::statement("ALTER TABLE statut_prospects MODIFY statut ENUM('0','1','2','3','4','5','6','7','8','9') NOT NULL");
        } catch (\Throwable $e) {
            // no-op
        }
    }
};

