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
        Schema::create('societes', function (Blueprint $table) {
            $table->id();
            $table->string('raison_sociale');
            $table->string('raison_sociale_concatene');
            $table->string('adresse')->nullable();
            $table->double('capital', 20, 2)->nullable();
            $table->double('id_fiscal', 20, 2)->nullable();
            $table->double('registre_commerce', 20, 2)->nullable();
            $table->string('nom_contact');
            $table->string('prenom_contact');
            $table->string('tel')->nullable();
            $table->string('email');
            $table->string('logo')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('societes');
    }
};
