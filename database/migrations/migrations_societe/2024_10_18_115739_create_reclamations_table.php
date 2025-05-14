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
        Schema::create('reclamations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bien_id')->constrained('biens')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('prestataire_id')->nullable()->constrained('prestataires')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->String('emplacements');
            $table->String('problemes')->nullable();
            $table->integer('statut')->default(0)->comment('
                1 =>En attente
                =>En cours
                3 =>Résolu
                4 ==>Non Résolu');
            $table->dateTime('date_reclamation');
            $table->String('motif')->nullable();
            // $table->foreignId('user_id_traiter')->constrained('users')->onDelete('cascade');
            $table->date('date_traitement')->nullable();
            $table->date('date_intervention')->nullable();
            $table->date('date_fin_intervention')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->String('commentaires')->nullable();
            $table->String('commentaire_trait')->nullable();
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reclamations');
    }
};
