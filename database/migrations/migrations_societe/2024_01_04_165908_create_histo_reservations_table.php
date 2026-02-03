<?php

use App\Enum\HistoReservationAction;
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
        Schema::create('historique_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->onDelete('cascade');
            $table->foreignId('bien_id')->nullable()->constrained('biens')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('ancien_id')->nullable();

            // Single action field with enum
            $table->enum('action', [
                HistoReservationAction::EN_ATTENTE->value,
                HistoReservationAction::VALIDATION->value,
                HistoReservationAction::MODIFICATION_RESERVATION->value,
                HistoReservationAction::REJET->value,
                HistoReservationAction::REJET_RELANCE_EN_COURS->value,
                HistoReservationAction::DESISTEMENT_DD->value,
                HistoReservationAction::DESISTEMENT_PROCHE->value,
                HistoReservationAction::DESISTEMENT_CO_RESERVATAIRE->value,
                HistoReservationAction::DESISTEMENT_PARTIEL->value,
                HistoReservationAction::CHANGEMENT_BIEN->value,
                HistoReservationAction::ATTESTATION_VENTE->value,
                HistoReservationAction::CONTRAT_VENTE->value,
                HistoReservationAction::Reconstitution_dossier->value,


            ])->comment('1=>En attente 2==>Validation 3=>Modification Réservation 4=>Rejet 5=>REJET-RELANCE EN COURS 6=>Desistement dd 7=>desistement proche 8=>desistement co reservataire 9=>desistement partiel 10=>changement de bien 11=>attestation de vente 12=>contrat de vente 13=>Reconstitution_dossier');

            $table->json('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historique_reservations');
    }
};
