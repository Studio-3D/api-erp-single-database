<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enum\TypeDesistement;
use App\Enum\TypeDesistementProfit;
use App\Enum\MotifDesistement;
use App\Enum\LienParente;
use App\Enum\ModePaiement;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('desistements', function (Blueprint $table) {
        $table->id();
        $table->foreignId('reservation_id')->constrained('reservations')->onDelete('cascade');
        $table->foreignId('reservation_id_new')->nullable()->constrained('reservations')->onDelete('cascade');
        $table->enum('type',[TypeDesistement::Désistement_Définitif->value,TypeDesistement::Désistement_Au_Profit->value,TypeDesistement::Changement_De_Bien->value])->comment('1=>definitif 2=>profit 3=>changement du bien');
        $table->enum('type_dp',[TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value,TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value,TypeDesistementProfit::Désistement_Partiel->value])->nullable()->comment('1=>ds Profit proche  2=>profit co reservataire 3=>profit ');
        $table->string('num_recu')->nullable();
        $table->integer('statut')->default(0)->comment('0=en attente 1=>valide 2=>rejete');
        $table->enum('motif',[MotifDesistement::Incapacité_Financière->value,
        MotifDesistement::Décès->value,
        MotifDesistement::Problème_familier->value,
        MotifDesistement::Mutation->value,
        MotifDesistement::Licenciement->value,
        MotifDesistement::Insatisfaction->value,
        MotifDesistement::Echange->value,
        MotifDesistement::Crédit_Bancaire_non_accordé->value,
        MotifDesistement::Client_imposé_à_la_TSC->value,
        MotifDesistement::Autre_investissement->value,
        MotifDesistement::Probléme_de_santé->value,
        ])->nullable()->comment('1=>Incapacité_Financière 2=>Décès 3=>Problème_familier 4=>Mutation 5=>Licenciement 6=>Insatisfaction 7=>Echange 8=>Credit Bancaire non accepte 9=>Client Imposé 10=>Autre Investissement  11=>Probleme de sante');
        $table->string('type_remb')->nullable();

        $table->enum('lien_parente',[LienParente::Parents->value,LienParente::Fils->value,LienParente::Fréres->value,LienParente::Soeurs->value,LienParente::Autre->value])->nullable()->comment('1=>parent 2=>fils 3=>frere 4=>soeur 5=>autre');
        $table->foreignId('bien_id_ancien')->nullable()->constrained('biens')->onDelete('cascade');
        $table->integer('bien_id_new')->nullable();
        $table->double('montant_a_ajouter')->nullable();
        $table->string('montant_a_ajouter_par_lettre')->nullable();

        $table->boolean('sr')->default(0);
        $table->foreignId('banque_id')->nullable()->constrained('banques')->onDelete('cascade');
        $table->bigInteger('numero_paiement')->nullable();
        $table->enum('mode_paiement',[ModePaiement::Espèce->value,ModePaiement::Chèque->value,ModePaiement::Chèque_Banque->value,ModePaiement::Chèque_Certifié->value,ModePaiement::Virement->value,ModePaiement::Versement->value])->nullable()->comment('1=>espece 2=>cheque 3=>cheque banque 4=>cheque certifie 5=>virement 6=>versement 7=>transfert dossier');
        $table->date('echeance')->nullable();

        $table->boolean('archive')->default(0)->comment('si desistement rejete apres re create desistement on fait archive=1');
        $table->integer('penalite_id')->nullable();
        $table->String('commentaire')->nullable();
        $table->String('commentaire_rejete')->nullable();
        $table->date('date_validation')->nullable();
        $table->foreignId('user_id_valider')->nullable()->constrained('users')->onDelete('cascade');
        $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
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
        Schema::dropIfExists('desistements');

    }
};
