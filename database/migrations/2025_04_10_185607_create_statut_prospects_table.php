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
            $table->enum('statut',[StatutProspectEnum::En_attente->value,StatutProspectEnum::Planification_RDV->value,StatutProspectEnum::Injoignable->value,StatutProspectEnum::Rappel->value,StatutProspectEnum::Converti_en_visite->value,StatutProspectEnum::Nouveau_appel->value,StatutProspectEnum::Affecte->value,StatutProspectEnum::Interesse->value,StatutProspectEnum::Perdu->value,StatutProspectEnum::Receptif->value])->comment('0=>En_attente 1=>Planification_RDV 2=>Injoignable 3=>Rappel 4=>Converti_en_visite 5=>Nouveau_appel 6=>Affecté 7=>Intéressé 8=>Perdu 9=>Réceptif');
            $table->foreignId('user_id_traite')->nullable()->constrained('users')->onDelete('cascade');
            $table->date('date_traitement')->nullable();
            $table->dateTime('rdv')->nullable();
            $table->date('date_rappel')->nullable();
            $table->string('commentaire')->nullable();
            $table->foreignId('visite_id')->nullable()->constrained('visites')->onDelete('cascade');
            $table->foreignId('appel_id')->nullable()->constrained('appels')->onDelete('cascade');

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
