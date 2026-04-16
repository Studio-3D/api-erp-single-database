<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing foreign key if exists
        Schema::table('prospects', function (Blueprint $table) {
            $this->dropForeignKeyIfExists('prospects', 'prospects_source_foreign');
        });

        // For MySQL - modify origin to enum with default value 'manuel' (NOT NULL)
        DB::statement("ALTER TABLE prospects MODIFY COLUMN origin ENUM('manuel', 'visite', 'whatsapp', 'facebook', 'landingPage', 'import', 'appel') NOT NULL DEFAULT 'manuel'");

        // Modify source column and add new foreign key (NOT NULL)
        Schema::table('prospects', function (Blueprint $table) {
            // Change source to NOT NULL and add foreign key
            $table->foreignId('source')->change();
            $table->foreign('source')->references('id')->on('sources')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $this->dropForeignKeyIfExists('prospects', 'prospects_source_foreign');
        });

        // Revert origin back to string (NOT NULL)
        DB::statement("ALTER TABLE prospects MODIFY COLUMN origin VARCHAR(255) NOT NULL");

        // Revert source back to foreignId without constraint
        Schema::table('prospects', function (Blueprint $table) {
            $table->foreignId('source')->change();
        });
    }

    private function dropForeignKeyIfExists($table, $foreignKeyName)
    {
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$table]);

        $foreignKeyNames = array_map(function($fk) {
            return $fk->CONSTRAINT_NAME;
        }, $foreignKeys);

        if (in_array($foreignKeyName, $foreignKeyNames)) {
            Schema::table($table, function (Blueprint $table) use ($foreignKeyName) {
                $table->dropForeign($foreignKeyName);
            });
        }
    }
};
