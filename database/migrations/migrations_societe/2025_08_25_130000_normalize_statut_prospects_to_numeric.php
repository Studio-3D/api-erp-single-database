<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            // Map any textual labels to numeric strings
            DB::statement("UPDATE statut_prospects SET statut='0' WHERE statut='En_attente'");
            DB::statement("UPDATE statut_prospects SET statut='1' WHERE statut='Planification_RDV'");
            DB::statement("UPDATE statut_prospects SET statut='2' WHERE statut='Injoignable'");
            DB::statement("UPDATE statut_prospects SET statut='3' WHERE statut='Rappel'");
            DB::statement("UPDATE statut_prospects SET statut='4' WHERE statut='Converti_en_visite'");
            DB::statement("UPDATE statut_prospects SET statut='5' WHERE statut='Nouveau_appel'");
            DB::statement("UPDATE statut_prospects SET statut='6' WHERE statut='Affecte'");
            DB::statement("UPDATE statut_prospects SET statut='7' WHERE statut='Interesse'");
            DB::statement("UPDATE statut_prospects SET statut='8' WHERE statut='Perdu'");
            DB::statement("UPDATE statut_prospects SET statut='9' WHERE statut='Receptif'");
        } catch (\Throwable $e) {
            // ignore if table/column not found
        }

        try {
            // Force enum to numeric strings only
            DB::statement(
                "ALTER TABLE statut_prospects MODIFY statut ENUM('0','1','2','3','4','5','6','7','8','9') NOT NULL"
            );
        } catch (\Throwable $e) {
            // ignore if already correct
        }
    }

    public function down(): void
    {
    }
};

