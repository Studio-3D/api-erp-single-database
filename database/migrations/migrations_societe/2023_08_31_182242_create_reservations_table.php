<?php

use App\Enum\ModeFinancement;
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
            $table->string('respo_dossier');
            $table->double('prix');
            $table->enum('mode_financement',[ModeFinancement::COMPTANT->name,ModeFinancement::CREDIT->name,ModeFinancement::INDECIS->name]);
            $table->date('date_reservation');
            $table->date('date_limite_reservation');
            $table->integer('nb_piece_jointe')->default(0)->nullable();
            $table->string('commentaire')->nullable();
            $table->foreignId('visite_id')->constrained('visites')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bien_id')->constrained('biens')->onDelete('cascade');
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
        Schema::dropIfExists('reservation');
    }
};
