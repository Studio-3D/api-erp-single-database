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
            $table->id('id');
            $table->string('nom');
            $table->string('code');
            $table->string('adresse');
            $table->date('date_autorisation_construction');
            $table->date('date_permis_habiter');
            $table->string('titre_foncier');
            $table->float('surface_terrain');
            $table->float('prix_acquisition');
            $table->integer('limite_annulation_reservation');
            $table->foreignId('type_id')->constrained('type_projets')->onDelete('cascade');
            $table->integer('nbre_tranches')->default(0);
            $table->integer('nbre_blocs')->default(0);
            $table->integer('nbre_immeubles')->default(0);
            $table->integer('nbre_biens')->default(0);
            $table->bigInteger('societe_id')->unsigned();
            $table->foreign('societe_id')->references('id')->on('societes');
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
