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
            $table->foreignId('reclamation_id')->nullable()->constrained('reclamations')->onDelete('cascade');
            $table->foreignId('bien_id')->nullable()->constrained('biens')->onDelete('cascade');
            $table->string('fichier');
            $table->string('type')->nullable();
            $table->integer('pj_scanner')->nullable()->default(0);
            $table->integer('active')->nullable()->default(1)->comment('active==>0 si commercial store piece jointe suite d\'un changement du bien on fait active 0 avant validation du désistement	');
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
