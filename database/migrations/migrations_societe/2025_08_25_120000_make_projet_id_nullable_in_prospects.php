<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Make projet_id nullable on in prospects table tenant (société) databases
        try {
            DB::statement("ALTER TABLE `prospects` MODIFY COLUMN `projet_id` BIGINT UNSIGNED NULL");
        } catch (\Throwable $e) {
            // Ignore if column already nullable or table missing on this tenant
        }
    }

    public function down(): void
    {
        // Revert to NOT NULL (will fail if rows contain NULLs)
        try {
            DB::statement("ALTER TABLE `prospects` MODIFY COLUMN `projet_id` BIGINT UNSIGNED NOT NULL");
        } catch (\Throwable $e) {
            // No-op
        }
    }
};

