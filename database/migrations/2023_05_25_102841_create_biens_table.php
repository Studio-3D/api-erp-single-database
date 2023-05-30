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
        Schema::create('biens', function (Blueprint $table) {
            $table->id();
            $table->string('propriete_dite_bien');
            $table->string('numero');
            $table->integer('niveau');
            $table->string('orientation');
            $table->boolean('conventionne');
            $table->float('prix_unitaire');
            $table->float('prix');
            $table->float('superficie_architecte');
            $table->float('superficie_habitable');
            $table->integer('nbre_facades');
            $table->float('superficie_parking');
            $table->float('superficie_box');
            $table->float('superficie_terrasse');
            $table->float('superficie_jardin');
            $table->string('titre_foncier');
            $table->string('etat');
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('type_id')->constrained('type_biens');        
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->foreignId('tranche_id')->constrained('tranches')->onDelete('cascade');
            $table->foreignId('bloc_id')->constrained('blocs')->onDelete('cascade');
            $table->foreignId('immeuble_id')->constrained('immeubles')->onDelete('cascade');

        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biens');
    }
};
