<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enum\ModePaiement;
use App\Enum\StatutReservationEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('historique_avances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avance_id')->constrained('avances')->onDelete('cascade');
            $table->double('montant');
            $table->string('montant_par_lettre');
            $table->string('num_recu');
            $table->bigInteger('numero_paiement')->nullable();
            $table->date('date_reglement');
            $table->enum('mode_paiement',[ModePaiement::Espèce->value,ModePaiement::Chèque->value,ModePaiement::Chèque_Banque->value,ModePaiement::Chèque_Certifié->value,ModePaiement::Virement->value,ModePaiement::Versement->value,ModePaiement::transfert_dossier->value]);
            $table->date('echeance')->nullable();
            $table->string('fichier')->nullable();
            $table->boolean('sr')->default(false);
            $table->string('commentaireAvance')->nullable();
            $table->string('commentaire_rejete')->nullable();
            $table->enum('statut',[StatutReservationEnum::Validé->value,StatutReservationEnum::Refusé->value,StatutReservationEnum::En_Attente->value]);
            $table->foreignId('banque_id')->nullable()->constrained('banques')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user_id_valider')->nullable()->constrained('users')->onDelete('cascade');
            $table->timestamp('date_validation')->nullable();
            $table->date('date_encaissement')->nullable();
            $table->string('num_remise')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historique_avances');
    }
};
