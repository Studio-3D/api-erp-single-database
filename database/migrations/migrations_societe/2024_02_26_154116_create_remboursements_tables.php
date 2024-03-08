<?php

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
        Schema::create('remboursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desistement_id')->constrained('desistements')->onDelete('cascade');
            $table->foreignId('reservation_id')->constrained('reservations')->onDelete('cascade');
            $table->integer('statut')->default(0)->comment('0=en attente 1=>valide 2=>rejete');
            $table->integer('etat')->nullable()->comment('0=>rem apres vente /1=> rem direct ou transfert ou transfer_remb_direct/ 2=> transfer_rem_apres_vente');
            $table->string('cheque')->nullable();
            $table->string('mode_rembourse')->nullable()->comment('direct /transfert_rem_direct/transfer_rem_apres_vente/apres_vente/tranfert');
            $table->double('s_avances');
            $table->double('montant_a_rembourser')->nullable();
            $table->string('montant_a_rembourser_par_lettre')->nullable();
            $table->date('date_rembourse')->nullable();
            $table->string('mode_rembourse_client')->nullable()->comment('cheque/virement');
            $table->bigInteger('num_paiement')->nullable();
            $table->string('pour_le_compte')->nullabel();
            $table->string('fichier_autorisation')->nullable();
            $table->double('montant_transfert')->nullable();
            $table->foreignId('dossier_id_transfert')->nullable()->constrained('reservations')->onDelete('cascade');
            $table->foreignId('user_id_valider')->nullable()->constrained('users')->onDelete('cascade');;
            //a voir les autres attributs
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('remboursementS');
    }
};
