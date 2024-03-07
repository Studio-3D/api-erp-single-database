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
        Schema::create('tranches', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->date('date_lancement')->nullable();
            $table->date('date_livraison')->nullable();
            $table->integer('niveau_etages')->nullable();
            $table->integer('nbre_blocs')->default(0);
            $table->integer('nbre_immeubles')->default(0);
            $table->integer('nbre_biens')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tranches');
    }
};
