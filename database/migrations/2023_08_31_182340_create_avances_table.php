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
            $table->float('montant');
            $table->string('num_recu');
            $table->string('montant_par_lettre');
            $table->integer('numero_paiement')->nullable();
            $table->date('date_reglement');
            $table->enum('mode_paiement',[ModePaiement::ESPECE->value,ModePaiement::CHEQUE->value,ModePaiement::CHEQUE_BANQUE->value,ModePaiement::CHEQUE_CERTIFIE->value,ModePaiement::VIREMENT->value,ModePaiement::VERSEMENT->value]);
            $table->date('echeance')->nullable();
            $table->string('fichier')->nullable();
            $table->boolean('sr')->default(false);
            $table->string('commentaireAvance')->nullable();
            $table->enum('statut',[StatutReservationEnum::EN_ATTENTE->value,StatutReservationEnum::REFUSER->value,StatutReservationEnum::VALIDER->value]);
            $table->foreignId('banque_id')->nullable()->constrained('banques')->onDelete('cascade');
            $table->foreignId('reservation_id')->constrained('reservations')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user_id_valider')->nullable()->constrained('users')->onDelete('cascade');
            $table->timestamp('date_validation')->nullable();
            $table->timestamp('date_encaissement')->nullable();
            $table->string('num_remise')->nullable();
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
