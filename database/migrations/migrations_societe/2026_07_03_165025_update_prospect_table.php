<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            // ✅ Champs personnalisés (pour les leads Facebook Ads)
            if (!Schema::hasColumn('prospects', 'type_bien')) {
                $table->string('type_bien')->nullable()->comment('Type de bien immobilier');
            }

            if (!Schema::hasColumn('prospects', 'budget')) {
                $table->string('budget')->nullable()->comment('Budget approximatif');
            }

            if (!Schema::hasColumn('prospects', 'residence')) {
                $table->string('residence')->nullable()->comment('Résidence (Maroc/MRE)');
            }

            // ✅ Champs Facebook Ads
            if (!Schema::hasColumn('prospects', 'facebook_id')) {
                $table->string('facebook_id')->nullable()->comment('ID Facebook de l\'utilisateur');
            }

            if (!Schema::hasColumn('prospects', 'facebook_lead_id')) {
                $table->string('facebook_lead_id')->nullable()->comment('ID du lead Facebook');
            }

            if (!Schema::hasColumn('prospects', 'facebook_ad_id')) {
                $table->string('facebook_ad_id')->nullable()->comment('ID de l\'annonce Facebook');
            }

            if (!Schema::hasColumn('prospects', 'facebook_ad_name')) {
                $table->string('facebook_ad_name')->nullable()->comment('Nom de l\'annonce Facebook');
            }

            if (!Schema::hasColumn('prospects', 'facebook_form_id')) {
                $table->string('facebook_form_id')->nullable()->comment('ID du formulaire Facebook');
            }


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $columns = [
                'type_bien',
                'budget',
                'residence',
                'facebook_id',
                'facebook_lead_id',
                'facebook_ad_id',
                'facebook_ad_name',
                'facebook_form_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('prospects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
