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
        // Add 'Suivi_dossier' (value 4) to interet enum
        try {
            DB::statement("ALTER TABLE visites MODIFY interet ENUM('1','2','3','5') NOT NULL COMMENT '1=>interesse 2=>recpetif 3=>perdu 5=>suivi dossier'");
        } catch (\Throwable $e) {
            \Log::warning('Could not modify interet column (might already have the value): ' . $e->getMessage());
        }

        // Add 'Nouvelle_avance' (value 6) to statut enum
        try {
            DB::statement("ALTER TABLE visites MODIFY statut ENUM('1','2','3','4','5','6') NULL COMMENT '1=>Pre reservation 2=>Vendu 3=>Pre reservation perdu 4=>reservation perdu 5=>Pré_Réservation_Vendu 6=>Nouvelle_avance'");
        } catch (\Throwable $e) {
            \Log::warning('Could not modify statut column (might already have the value): ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'Suivi_dossier' (value 4) from interet enum
        try {
            DB::statement("ALTER TABLE visites MODIFY interet ENUM('1','2','3') NOT NULL COMMENT '1=>interesse 2=>recpetif 3=>perdu'");
        } catch (\Throwable $e) {
            \Log::warning('Could not revert interet column: ' . $e->getMessage());
        }

        // Remove 'Nouvelle_avance' (value 6) from statut enum
        try {
            DB::statement("ALTER TABLE visites MODIFY statut ENUM('1','2','3','4','5') NULL COMMENT '1=>Pre reservation 2=>Vendu 3=>Pre reservation perdu 4=>reservation perdu 5=>Pré_Réservation_Vendu'");
        } catch (\Throwable $e) {
            \Log::warning('Could not revert statut column: ' . $e->getMessage());
        }
    }
};
