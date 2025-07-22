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
            // Migration pour la table des créneaux disponibles
        Schema::create('creneaux_occupes', function (Blueprint $table) {
            $table->id();
            $table->datetime('debut');
            $table->datetime('fin');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->boolean('disponible')->default(true)->comment('1==>disponible 0==>occupé');
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->onDelete('cascade');
            $table->String('type')->nullable()->comment('1=>compromis de vente ,2=>contrat de vente 0=>en proposition')->default('0');
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creneaux_occupes');
    }
};
