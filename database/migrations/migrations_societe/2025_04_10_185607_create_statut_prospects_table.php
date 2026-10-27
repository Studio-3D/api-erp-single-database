<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enum\StatutProspectEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('statut_prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained('prospects')->onDelete('cascade');
            $table->enum('statut', [
                StatutProspectEnum::En_attente->value,
                StatutProspectEnum::Planification_RDV->value,
                StatutProspectEnum::Injoignable->value,
                StatutProspectEnum::Rappel->value,
                StatutProspectEnum::Converti_en_visite->value,
                StatutProspectEnum::Nouveau_appel->value,
                StatutProspectEnum::Affecte->value,
                StatutProspectEnum::Interesse->value,
                StatutProspectEnum::Perdu->value,
                StatutProspectEnum::Receptif->value,
                StatutProspectEnum::Converti_en_client->value,
                StatutProspectEnum::WhatsApp_Envoye->value,
                StatutProspectEnum::pre_reservation->value,
                StatutProspectEnum::Reaffecte->value,
                StatutProspectEnum::Qualifie->value,
                StatutProspectEnum::En_veille->value,
                StatutProspectEnum::Invalide->value,
                StatutProspectEnum::Visite_non_honoree->value,
            ])->comment(
                '0=>Nouveau 1=>Visite programmée 2=>Injoignable 3=>A rappeler '
                .'4=>Converti en Visite 5=>Nouveau 6=>Affecté 7=>Intéressé '
                .'8=>Perdu 9=>Réceptif 10=>Visite effectuée 11=>WhatsApp Envoyé '
                .'12=>Pré réservation 13=>Réaffecté 14=>Qualifié 15=>En veille '
                .'16=>Invalide 17=>Visite non honorée'
            );

            $table->foreignId('user_id_traite')->nullable()->constrained('users')->onDelete('cascade');
            $table->date('date_traitement')->nullable();
            $table->dateTime('rdv')->nullable();
            $table->date('date_rappel')->nullable();
            $table->string('commentaire')->nullable();
            $table->foreignId('visite_id')->nullable()->constrained('visites')->onDelete('cascade');
            $table->foreignId('appel_id')->nullable()->constrained('appels')->onDelete('cascade');

            // ⬇️ Nouvelles colonnes
            $table->integer('type_traitement_rdv_relance')
                  ->nullable()
                  ->comment('0 en attent //1 manuelle //2 automatique 3// nouvel relance/rdv');
            $table->dateTime('date_traitement_rdv_relance')->nullable();
            $table->integer('user_id_traite_rdv_relance')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statut_prospects');
    }
};
