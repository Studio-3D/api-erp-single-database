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
        Schema::create('echeances_projet', function (Blueprint $table) {
            //etapes Projet
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->string('description');
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->date('date_debut_prevu');
            $table->date('date_fin_prevu');
            $table->enum('etat', [0,1,2])->default(0)->comment('0=>non Commencé 1=>Terminé 2 En cours');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('echeances_projet');
    }
};
