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
            if (!Schema::hasColumn('prospects', 'commercial_affecte')) {
                $table->boolean('commercial_affecte')->nullable()->after('source');
            }
            if (!Schema::hasColumn('prospects', 'affecte_par_admin_id')) {
                $table->unsignedBigInteger('affecte_par_admin_id')->nullable()->after('commercial_affecte');
            }
            if (!Schema::hasColumn('prospects', 'traite_par_user_id')) {
                $table->unsignedBigInteger('traite_par_user_id')->nullable()->after('affecte_par_admin_id');
            }
            if (!Schema::hasColumn('prospects', 'date_affectation')) {
                $table->timestamp('date_affectation')->nullable()->after('traite_par_user_id');
            }
            if (!Schema::hasColumn('prospects', 'date_traitement')) {
                $table->timestamp('date_traitement')->nullable()->after('date_affectation');
            }
             if (!Schema::hasColumn('prospects', 'facebook_id')) {
                $table->string('facebook_id')->nullable()->after('date_traitement');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'nb_prospects')) {
                $table->integer('nb_prospects')->default(0)->after('solde_conge');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            if (Schema::hasColumn('prospects', 'commercial_affecte')) {
                $table->dropColumn('commercial_affecte');
            }
            if (Schema::hasColumn('prospects', 'affecte_par_admin_id')) {
                $table->dropColumn('affecte_par_admin_id');
            }
            if (Schema::hasColumn('prospects', 'traite_par_user_id')) {
                $table->dropColumn('traite_par_user_id');
            }
            if (Schema::hasColumn('prospects', 'date_affectation')) {
                $table->dropColumn('date_affectation');
            }
            if (Schema::hasColumn('prospects', 'date_traitement')) {
                $table->dropColumn('date_traitement');
            }
             if (Schema::hasColumn('prospects', 'facebook_id')) {
                $table->string('facebook_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'nb_prospects')) {
                $table->dropColumn('nb_prospects');
            }
        });
    }
};
