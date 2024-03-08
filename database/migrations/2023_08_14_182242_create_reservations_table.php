<?php

use App\Enum\ModeFinancement;
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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ancien_id')->nullable();
            $table->integer('nb_acquereurs');
            $table->string('code_reservation')->nullable();
            $table->double('prix');
            $table->enum('mode_financement',[ModeFinancement::Comptant->value,ModeFinancement::Crédit->value,ModeFinancement::Indécis->value]);
            $table->enum('statut',[StatutReservationEnum::Validé->value,StatutReservationEnum::Refusé->value,StatutReservationEnum::En_Attente->value,StatutReservationEnum::Annulé->value]);
            $table->date('date_reservation');
            $table->string('commentaire')->nullable();
            $table->integer('etat')->default(1)
            ->comment(' active=1;
            desist_definitif=2;
            desist_profit_proche=3;
            desist_profit_co=4;
            desist_partiel=5;
            desist_change_bien=6;');
            $table->double('prix_remise')->nullable();
            $table->string('prix_remise_lettre')->nullable();
            $table->double('prix_forfetaire')->nullable();
            $table->string('prix_forfetaire_lettre')->nullable();
            $table->double('code_desistement')->nullable()->comment('code partagé entre les reservations du meme code reservation=>On utilise dans historique desistement');
            $table->foreignId('visite_id')->nullable()->constrained('visites')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bien_id')->constrained('biens')->onDelete('cascade');
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
