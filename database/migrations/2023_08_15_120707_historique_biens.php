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
        Schema::create('historique_biens', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            //1=disponible, 2=pré-réservé, 3=réservé, 4=bloqué
            $table->string('description');
            $table->bigInteger('bien_id')->unsigned();
            $table->foreign('bien_id')->references('id')->on('biens');
            $table->bigInteger('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreignId('visite_id')->nullable()->constrained('visites')->onDelete('cascade');
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['visite_id','reservation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
