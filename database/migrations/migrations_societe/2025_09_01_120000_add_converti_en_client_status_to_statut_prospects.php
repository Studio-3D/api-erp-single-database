<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE statut_prospects MODIFY statut ENUM('0','1','2','3','4','5','6','7','8','9','10') NOT NULL");
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE statut_prospects MODIFY statut ENUM('0','1','2','3','4','5','6','7','8','9') NOT NULL");
        } catch (\Throwable $e) {
        }
    }
};

