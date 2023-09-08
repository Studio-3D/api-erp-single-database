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
        Schema::create('type_bien_projets', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('type_bien_id')->unsigned();
            $table->foreign('type_bien_id')->references('id')->on('type_biens');
            $table->bigInteger('projet_id')->unsigned();
            $table->foreign('projet_id')->references('id')->on('projets');
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
        //
    }
};
