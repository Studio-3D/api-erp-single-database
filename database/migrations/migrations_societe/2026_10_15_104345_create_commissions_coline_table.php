<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('commissions_coline', function (Blueprint $table) {
            $table->id();
            $table->integer('reservation_id');
            $table->integer('etat')->default(0)
                ->comment('0=>non traite 1=>moitie attent 2=>moitie traite 3=>complet attent 4=>complet traite');
            $table->double('montant')->nullable();
            $table->string('montant_lettre')->nullable();
            $table->dateTime('date_traitement')->nullable();
            $table->integer('user_id');
            $table->integer('user_id_traite')->nullable();
            $table->integer('enable')->default(1)
                ->comment('1=>sans historique 2=>il a des historiques');
            $table->timestamps();
            $table->index('reservation_id');
            $table->index('user_id');
            $table->index('etat');
        });
    }

    public function down()
    {
        Schema::dropIfExists('commissions_coline');
    }
};
