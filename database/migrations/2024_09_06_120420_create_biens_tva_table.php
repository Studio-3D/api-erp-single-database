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
        Schema::create('biens_tva', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tranche_id')->constrained('tranches')->onDelete('cascade');
            $table->foreignId('bien_id')->constrained('biens')->onDelete('cascade');
            $table->double('tva', 20, 2)->default(0);
            $table->double('prix_ttc', 20, 2)->default(0);
            $table->double('qp_terrain_percent', 20, 2)->default(0);
            $table->double('qp_terrain_valeur', 20, 2)->default(0);
            $table->double('prix_vente_ht_hors_terrain', 20, 2)->default(0);
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biens_tva');
    }
};
