<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'prospects')) {
            // If the prospects column does not exist, we skip this migration
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            // Add nb_prospects column
            $table->integer('nb_prospects')->default(0)->after('solde_conge');
        });

        // Migrate existing data from prospects JSON to nb_prospects count
        DB::statement("
            UPDATE users 
            SET nb_prospects = CASE 
                WHEN prospects IS NULL OR prospects = 'null' OR prospects = '[]' THEN 0
                ELSE JSON_LENGTH(prospects)
            END
        ");

        Schema::table('users', function (Blueprint $table) {
            // Remove the old prospects column
            $table->dropColumn('prospects');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add back prospects column
            $table->json('prospects')->nullable()->after('solde_conge');
            // Remove nb_prospects column
            $table->dropColumn('nb_prospects');
        });
    }
};
