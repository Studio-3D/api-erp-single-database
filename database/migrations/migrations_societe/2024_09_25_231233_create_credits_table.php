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
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('banque_id')->constrained('banques')->onDelete('cascade');
            $table->string('num_contrat');
            $table->string('piece_jointe');
            $table->date('date');
            $table->double('montant_capital',20,2);
            $table->double('frais_dossier',20,places: 2);
            $table->date('de');
            $table->date('a');
            $table->integer('nb_mois');
            $table->double('taux_interet',20,2);
            $table->double('montant_interet',20,2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credits');

    }
};
