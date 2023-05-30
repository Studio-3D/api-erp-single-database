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
        Schema::create('immeubles', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('titre_foncier');
            $table->unsignedBigInteger('projet_id');
            $table->unsignedBigInteger('tranche_id');
            $table->unsignedBigInteger('bloc_id');
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->foreignId('tranche_id')->constrained('tranches')->onDelete('cascade');
            $table->foreignId('bloc_id')->constrained('blocs')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('immeubles');
    }
};
