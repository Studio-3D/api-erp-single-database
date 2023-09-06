<?php

use App\Enum\ModePaiement;
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
            $table->double('prix');
            $table->double('reste_avance');
            $table->double('reste');
            $table->float('montant');
            $table->string('montant_lettre');
            $table->enum('mode_paiement',[ModePaiement::ESPECE->name,ModePaiement::CHEQUE->name,ModePaiement::CHEQUE_BANQUE->name,ModePaiement::CHEQUE_CERTIFIE->name,ModePaiement::VIREMENT->name,ModePaiement::VERSEMENT->name]);
            $table->date('echance');
            $table->boolean('sans_recu')->default(false);
            $table->integer('nbr_place_jointe');
            $table->foreignId('banque_id')->constrained('banques')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avance');
    }
};
