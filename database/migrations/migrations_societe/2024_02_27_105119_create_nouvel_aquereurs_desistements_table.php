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
        Schema::create('nouvel_aquereurs_desistements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desistement_id')->constrained('desistements')->onDelete('cascade');
            $table->string('cin');
            $table->string('nom');
            $table->string('prenom');
            $table->string('telephone');
            $table->integer('pourcentage');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nouvel_aquereurs_desistements');
    }
};
