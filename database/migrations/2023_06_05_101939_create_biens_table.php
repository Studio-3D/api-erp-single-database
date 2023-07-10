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
            $table->boolean('conventionne')->nullable();
            $table->double('prix_unitaire', 12, 2);
            $table->double('prix', 12, 2);
            $table->float('superficie_architecte');
            $table->float('superficie_habitable')->nullable();
            $table->integer('nbre_facades');
            $table->float('superficie_parking')->nullable();
            $table->float('superficie_box')->nullable();
            $table->float('superficie_terrasse')->nullable();
            $table->float('superficie_jardin')->nullable();
            $table->string('titre_foncier')->nullable();
            $table->string('etat'); //1=disponible, 2=pré-réservé, 3=réservé, 4=bloqué
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('type_id')->constrained('type_biens');
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->foreignId('tranche_id')->constrained('tranches')->onDelete('cascade')->nullable();
            $table->foreignId('bloc_id')->constrained('blocs')->onDelete('cascade')->nullable();
            $table->foreignId('immeuble_id')->constrained('immeubles')->onDelete('cascade')->nullable();

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
