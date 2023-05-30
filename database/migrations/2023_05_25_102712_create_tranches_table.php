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
            $table->unsignedBigInteger('projet_id');
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->date('date_lancement');
            $table->date('date_livraison');
            $table->integer('niveau_etages');
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
