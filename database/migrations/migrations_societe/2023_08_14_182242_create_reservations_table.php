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
            $table->integer('nb_acquereurs');
            $table->string('code_reservation')->nullable();
            $table->double('prix');
            $table->enum('mode_financement',[ModeFinancement::COMPTANT->value,ModeFinancement::CREDIT->value,ModeFinancement::INDECIS->value]);
            $table->enum('statut',[StatutReservationEnum::VALIDER->value,StatutReservationEnum::REFUSER->value,StatutReservationEnum::EN_ATTENTE->value,StatutReservationEnum::ANNULLER->value]);
            $table->date('date_reservation');
            $table->date('date_limite_reservation');
            $table->string('commentaire')->nullable();
            $table->double('prix_remise')->nullable();
            $table->string('prix_remise_lettre')->nullable();
            $table->double('prix_forfetaire')->nullable();
            $table->string('prix_forfetaire_lettre')->nullable();
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
