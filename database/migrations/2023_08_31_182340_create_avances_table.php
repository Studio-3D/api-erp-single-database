<?php

use App\Enum\ModePaiement;
use App\Enum\Status;
use App\Enum\StatutReservationEnum;
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
        Schema::create('avances', function (Blueprint $table) {
            $table->id();
            $table->double('montant');
            $table->string('num_recu');
            $table->string('ancien_recu')->nullable();
            $table->string('montant_par_lettre');
            $table->bigInteger('numero_paiement')->nullable();
            $table->date('date_reglement');
            $table->enum('mode_paiement',[ModePaiement::Espèce->value,ModePaiement::Chèque->value,ModePaiement::Chèque_Banque->value,ModePaiement::Chèque_Certifié->value,ModePaiement::Virement->value,ModePaiement::Versement->value]);
            $table->date('echeance')->nullable();
            $table->string('fichier')->nullable();
            $table->string('recu_scanne')->nullable();
            $table->boolean('sr')->default(false);
            $table->string('commentaireAvance')->nullable();
            $table->enum('statut',[StatutReservationEnum::Validé->value,StatutReservationEnum::Refusé->value,StatutReservationEnum::En_Attente->value]);
            $table->foreignId('banque_id')->nullable()->constrained('banques')->onDelete('cascade');
            $table->foreignId('reservation_id')->constrained('reservations')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user_id_valider')->nullable()->constrained('users')->onDelete('cascade');
            $table->timestamp('date_validation')->nullable();
            $table->date('date_encaissement')->nullable();
            $table->string('num_remise')->nullable();
            $table->foreignId('dossier_id_transfert')->nullable()->constrained('reservations')->onDelete('cascade');
            $table->integer('desistement_id')->nullable();
            $table->foreignId('reservation_id_ancien')->nullable()->constrained('reservations')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avances');
    }
};
