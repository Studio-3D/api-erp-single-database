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
            $table->enum('mode_financement',[ModeFinancement::COMPTANT->name,ModeFinancement::CREDIT->name,ModeFinancement::INDECIS->name]);
            $table->enum('statut',[StatutReservationEnum::VALIDER->name,StatutReservationEnum::REFUSER->name,StatutReservationEnum::EN_ATTENTE->name,StatutReservationEnum::ANNULLER->name]);
            $table->date('date_reservation');
            $table->date('date_limite_reservation');
            $table->string('commentaire')->nullable();
            $table->double('montant_encaisse')->nullable()->default(0);
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
