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
        Schema::create('fiche_transmissions', function (Blueprint $table) {
            $table->id();
            $table->string('num_recu');
            $table->foreignId('id_avance')->constrained('avances')->nullable()->onDelete('cascade');
            $table->integer('id_penalite')->nullable()->comment('Table Penalite');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiche_transmissions');
    }
};
