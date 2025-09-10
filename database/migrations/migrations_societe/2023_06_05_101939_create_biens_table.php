<?php

use App\Enum\EtatBien;
use App\Enum\OrientationEnum;
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
            $table->string('description')->nullable();
            $table->integer('niveau')->nullable();
            $table->enum('orientation', [OrientationEnum::N->name, OrientationEnum::E->name, OrientationEnum::S->name, OrientationEnum::O->name, OrientationEnum::N_E->name, OrientationEnum::N_O->name,OrientationEnum::S_E->name, OrientationEnum::S_O->name]);
            $table->boolean('conventionne')->default(false);
            $table->double('prix_unitaire', 12, 2);
            $table->double('prix', 20, 2);
            $table->double('prix_parking', 12, 2)->nullable();;
            $table->string('num_parking')->nullable();;
            $table->string('num_box')->nullable();;
            $table->double('prix_box', 12, 2)->nullable();;
            $table->double('avance_minimale', 12, 2);
            $table->double('superficie_architecte', 12, 2);
            $table->double('superficie_habitable', 12, 2)->nullable();
            $table->integer('nbre_facades');
            $table->float('superficie_parking')->nullable();
            $table->float('superficie_box')->nullable();
            $table->float('superficie_terrasse')->nullable();
            $table->float('superficie_terrasse_calculer')->nullable();
            $table->float('superficie_balcon')->nullable();
            $table->float('superficie_balcon_calculer')->nullable();
            $table->double('superficie_jardin', 12, 2)->nullable();
            $table->double('superficie_jardin_calculer', 12, 2)->nullable();
            $table->string('titre_foncier')->nullable();
            $table->double('superficie_total', 12, 2);
            $table->double('superficie_vendable', 12, 2);
            $table->enum('etat', [EtatBien::DISPONIBLE->name, EtatBien::PRE_RESERVATION->name, EtatBien::RESERVATION->name, EtatBien::BLOQUE->name, EtatBien::VENDU->name, EtatBien::ENCOURS_DE_PROPOSITION->name]); //1=disponible, 2=pré-réservé, 3=réservé, 4=bloqué
            $table->foreignId('type_id')->constrained('type_biens');
            $table->foreignId(column: 'projet_id')->constrained('projets')->onDelete('cascade');
            $table->foreignId('tranche_id')->nullable()->constrained('tranches')->onDelete('cascade');
            $table->foreignId('bloc_id')->nullable()->constrained('blocs')->onDelete('cascade');
            $table->foreignId('immeuble_id')->nullable()->constrained('immeubles')->onDelete('cascade');
            $table->foreignId('vue_id')->nullable()->constrained('vues')->onDelete('cascade');
            $table->foreignId('typologie_id')->nullable()->constrained('typologies')->onDelete('cascade');
            $table->integer('desistement_id')->nullable();
            $table->timestamps();
            $table->softDeletes();


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
