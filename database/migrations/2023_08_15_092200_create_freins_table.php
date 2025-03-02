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
        Schema::create('freins', function (Blueprint $table) {
            $table->id();
            $table->double('prix_min', 12, 2)->nullable();
            $table->double('prix_max', 12, 2)->nullable();
            $table->double('superficie_min', 12, 2)->nullable();
            $table->double('superficie_max', 12, 2)->nullable();
            $table->boolean('etat')->default(false)->comment('1 attent 2 existe_bien_disponible 3=>traite 4=>descativé par user  5 =>desactive par appel en frein bien disponible 6=>create new frein by appel bien disponible');
            $table->double('avance')->nullable();
            $table->boolean('tranche')->default(false);
            $table->boolean('orientation' )->default(false);
            $table->boolean('etage')->default(false);
            $table->boolean('vue')->default(false);
            $table->boolean('typologie')->default(false);
            $table->string('commentaire')->nullable();
            $table->string('description_autre')->nullable();
            $table->foreignId('visite_id')->nullable()->constrained('visites')->onDelete('cascade');
            $table->foreignId('traite_appel_id')->nullable()->constrained('traitements_appels')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::dropIfExists('freins');
    }
};
