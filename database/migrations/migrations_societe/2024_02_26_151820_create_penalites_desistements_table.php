<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enum\ModePaiement;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('penalites_desistements', function (Blueprint $table) {
        $table->id();
        $table->foreignId('desistement_id')->constrained('desistements')->onDelete('cascade');
        $table->string('num_recu');
        $table->integer('statut')->default(0)->comment('0=en attente 1=>valide 2=>rejete');
        $table->double('montant');
        $table->string('montant_par_lettre');
        $table->string('penalite_par');
        $table->string('mode_penalite')->comment('10% 15%... montant');
        $table->boolean('sr')->default(0);
        $table->foreignId('banque_id')->nullable()->constrained('banques')->onDelete('cascade');
        $table->bigInteger('numero_paiement')->nullable();
        $table->enum('mode_paiement',[ModePaiement::Espèce->value,ModePaiement::Chèque->value,ModePaiement::Chèque_Banque->value,ModePaiement::Chèque_Certifié->value,ModePaiement::Virement->value,ModePaiement::Versement->value])->nullable();
        $table->date('echeance')->nullable();
        $table->string('num_remise')->nullable();
        $table->date('date_encaissement')->nullable();
        $table->timestamp('date_validation')->nullable();
        $table->string('commentaire_validation')->nullable();
        $table->foreignId('user_id_valider')->nullable()->constrained('users')->onDelete('cascade');
        $table->timestamps();
        $table->softDeletes();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penalites_desistements');
    }
};
