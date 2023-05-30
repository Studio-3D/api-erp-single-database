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
        Schema::create('projets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nom');
            $table->string('code');
            $table->string('adresse');
            $table->date('date_autorisation_construction');
            $table->date('date_permis_habiter');
            $table->string('titre_foncier');
            $table->float('surface_terrain');
            $table->float('prix_acquisition');
            $table->integer('limite_annulation_reservation');
            $table->foreignId('type_id')->constrained('type_projets');
            $table->integer('nbr_tranches')->default(0);
            $table->integer('nbr_blocs')->default(0);
            $table->integer('nbr_immeubles')->default(0);
            $table->integer('nbr_bien')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projets');
    }
};
