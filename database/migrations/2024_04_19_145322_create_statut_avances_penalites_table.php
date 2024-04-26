<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enum\StatutReservationEnum;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('statut_avances_penalites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penalite_id')->nullable()->constrained('penalites_desistements')->onDelete('cascade');
            $table->foreignId('avance_id')->nullable()->constrained('avances')->nullable()->onDelete('cascade');
            $table->enum('statut',[StatutReservationEnum::Validé->value,StatutReservationEnum::Refusé->value]);
            $table->foreignId('user_id_valider')->constrained('users')->onDelete('cascade');
            $table->dateTime('date_validation')->nullable();
            $table->string('commentaire')->nullable();
            $table->date('date_encaissement')->nullable();
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
        Schema::dropIfExists('statut_avances_penalites');

    }
};
