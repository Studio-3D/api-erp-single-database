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
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn(['affecte_par_admin_id', 'traite_par_user_id', 'date_affectation', 'date_traitement']);
        });
    }
};
