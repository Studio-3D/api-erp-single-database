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
        Schema::create('freins', function (Blueprint $table) {
            $table->id();
            $table->float('prix_min')->nullable();
            $table->float('prix_max')->nullable();
            $table->float('superficie_min')->nullable();
            $table->float('superficie_max')->nullable();
            $table->boolean('liste_attente')->default(false);
            $table->double('avance')->nullable();
            $table->boolean('tranche')->default(false);
            $table->boolean('orientation' )->default(false);
            $table->boolean('etage')->default(false);
            $table->boolean('vue')->default(false);
            $table->boolean('typologie')->default(false);
            $table->foreignId('visite_id')->nullable()->constrained('visites')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::dropIfExists('freins');
    }
};
