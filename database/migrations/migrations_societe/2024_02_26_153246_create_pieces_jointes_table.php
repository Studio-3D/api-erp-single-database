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
        Schema::create('pieces_jointes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->onDelete('cascade');
            $table->foreignId('desistement_id')->nullable()->constrained('desistements')->onDelete('cascade');
            $table->foreignId('avance_id')->nullable()->constrained('avances')->onDelete('cascade');
            $table->foreignId('penalite_id')->nullable()->constrained('penalites_desistements')->onDelete('cascade');
            $table->string('piece_jointe');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pieces_jointes');
    }
};
