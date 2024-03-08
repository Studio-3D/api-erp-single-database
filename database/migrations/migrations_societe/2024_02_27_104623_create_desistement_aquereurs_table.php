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
        Schema::create('aquereurs_desistements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desistement_id')->constrained('desistements')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('aq_id')->constrained('aquereurs')->onDelete('cascade');
            $table->integer('pourcentage')->nullable();
            $table->string('type')->string('desisteurs / profit /non_desisteur');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aquereurs_desistements');
    }
};
