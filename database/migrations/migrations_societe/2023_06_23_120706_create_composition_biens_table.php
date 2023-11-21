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
        Schema::create('composition_biens', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('bien_id')->unsigned();
            $table->foreign('bien_id')->references('id')->on('biens');
            $table->integer('nbre_chambres')->default(0);
            $table->integer('nbre_salons')->default(0);
            $table->integer('nbre_sdb')->default(0);
            $table->integer('nbre_cuisines')->default(0);
            $table->integer('nbre_halls')->default(0);
            $table->integer('nbre_terasses')->default(0);
            $table->integer('nbre_balcons')->default(0);
            $table->integer('nbre_buanderies')->default(0);
            $table->integer('nbre_placards')->default(0);
            $table->integer('nbre_receptions')->default(0);
            $table->timestamps();
            $table->softDeletes();


            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('composition_biens');
    }
};
