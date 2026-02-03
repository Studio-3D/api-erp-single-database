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
            $table->foreignId('aquereur_id')->nullable()->constrained('aquereurs')->onDelete('cascade');
            $table->integer('statut')->default(0)->comment(
                '0 =>att de pre remboursement
                1=>att accusé du cheque
                2 =>att decaissement
                3 ==>list des accusé');
            $table->integer('etat')->nullable()->comment('0=>rem apres vente /1=> rem direct ou transfert ou transfer_remb_direct/ 2=> transfer_rem_apres_vente');
            $table->string('cheque')->nullable();
            $table->string('mode_rembourse')->nullable()->comment('direct /transfert_rem_direct/transfer_rem_apres_vente/apres_vente/tranfert');
            $table->double('s_avances');
            $table->double('montant_a_rembourser')->nullable();
            $table->string('montant_a_rembourser_par_lettre')->nullable();
            $table->date('date_rembourse')->nullable();
            $table->string('mode_rembourse_client')->nullable()->comment('cheque/virement');
            $table->bigInteger('num_paiement')->nullable();
            $table->string('pour_le_compte')->nullable();
            $table->string('fichier_autorisation')->nullable();
            $table->string('nom_autorisation')->nullable();
            $table->double('montant_transfert')->nullable();
            $table->dateTime('remis_le')->nullable();
            $table->string('cheque_client_signe')->nullable();
            $table->string('user_id_remis')->nullable();

            $table->dateTime('date_accuse')->nullable();
            $table->dateTime('date_decaissement')->nullable();
            $table->foreignId('banque_id')->nullable()->constrained('banques')->onDelete('cascade');
            $table->boolean('archive')->default(0)->comment('si desistement rejete apres re create desistement on fait archive=1');
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
