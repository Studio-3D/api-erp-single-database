<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enum\StatutClientEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vérifier si la table existe déjà avant de la créer
        if (!Schema::hasTable('statut_clients')) {
            Schema::create('statut_clients', function (Blueprint $table) {
                $table->id();

                // Correction: 'clients' au pluriel (convention Laravel)
                $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');

                $table->enum('statut', [
                StatutClientEnum::Nouvelle_Avance->value,
                StatutClientEnum::Suivi_Avancement_travaux->value,
                StatutClientEnum::Demande_des_documents->value,
                StatutClientEnum::Autre->value,
                StatutClientEnum::Creation_Reservation->value,
                StatutClientEnum::Ajout_Rendez_Vous->value,
                StatutClientEnum::Signature_Attestation_Vente->value,
                StatutClientEnum::Signature_Contrat_Vente->value,
                StatutClientEnum::Remise_Cle->value,
                StatutClientEnum::Desistement_dd->value,
                StatutClientEnum::Desistement_dp_profit->value,
                StatutClientEnum::Desistement_dp_co->value,
                StatutClientEnum::Desistement_dp_partiel->value,
                StatutClientEnum::Desistement_change_bien->value,
               StatutClientEnum::Penalite_valide->value,
                StatutClientEnum::Remise_du_remboursement->value, StatutClientEnum::Decaissement_effctue->value, StatutClientEnum::Penalite_rejete->value,
                ])->default(StatutClientEnum::Autre->value)->comment(' 1=>Nouvelle_Avance, 2=>Suivi_Avancement_travaux, 3=>Demande_des_documents, 4=>Autre, 5=>Creation_Reservation, 6=>Ajouter_Rdv, 7=>Signature_Attestation_Vente, 8=>Signer_Contrat_Vente, 9=>Remise_Cle, 10=>Desistement_dd, 11=>Desistement_dp_profit, 12=>Desistement_dp_co, 13=>Desistement_dp_partiel, 14=>Desistement_change_bien, 15=>Penalite Valide, 16=>Remise Remboursement,17=>dacaissement effectue ,19=>penalite rejete');
                $table->string('commentaire')->nullable();
                $table->date('date_traitement')->nullable();
                $table->foreignId('user_id_traite')->nullable()->constrained('users')->onDelete('cascade');
                // Correction: 'visites' au pluriel
                $table->foreignId('visite_id')->nullable()->constrained('visites')->onDelete('cascade');
                $table->foreignId('avance_id')->nullable()->constrained('avances')->onDelete('cascade');
                $table->foreignId('compromis_vente_id')->nullable()->constrained('compromis_vente')->onDelete('cascade');
                $table->foreignId('contrat_vente_id')->nullable()->constrained('contrat_ventes')->onDelete('cascade');
                $table->foreignId('reservation_id')->nullable()->constrained('reservations')->onDelete('cascade');
                $table->foreignId('desistement_id')->nullable()->constrained('desistements')->onDelete('cascade');
                $table->foreignId('penalite_id')->nullable()->constrained('penalites_desistements')->onDelete('cascade');
                $table->foreignId('remboursement_id')->nullable()->constrained('remboursements')->onDelete('cascade');
                $table->foreignId('rdv_id')->nullable()->constrained('rendez_vous')->onDelete('cascade');
                $table->foreignId('remise_cle_id')->nullable()->constrained('remise_cles')->onDelete('cascade');
                $table->timestamps();
                $table->softDeletes();
            });

            // Message pour confirmer la création
            echo "Table 'statut_clients' créée avec succès.\n";
        } else {
            echo "Table 'statut_clients' existe déjà.\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statut_clients');
    }
};
