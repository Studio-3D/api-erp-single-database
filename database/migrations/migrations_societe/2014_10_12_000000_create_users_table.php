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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id_origin')->unique();//id de l'utilisateur dans la base de donnée mère
            //$table->integer('user_id_origin')->unique();//id de l'utilisateur dans la base de donnée mère
            $table->string('name');
            $table->string('prenom');
            $table->string('email');
            $table->tinyInteger('role');
            $table->string('gender')->nullable();
            $table->string('phone')->nullable();
            $table->string('photo')->nullable();
            $table->string('password');
            $table->integer('nb_appel_recu')->nullable();
            $table->integer('nb_appel_traite')->nullable();
            $table->string('remember_token')->nullable();
            $table->string('cin')->nullable();
            $table->date('date_embauche')->nullable();
            $table->string('niveau_etude')->nullable();
            $table->string('adresse')->nullable();
            $table->integer('cnss')->nullable();
            $table->integer('is_actif')->default(1);
            $table->string('fonction')->nullable();
            $table->integer('solde_conge')->nullable();
            $table->integer('is_connected')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
