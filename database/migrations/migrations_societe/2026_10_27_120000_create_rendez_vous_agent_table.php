<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table des rendez-vous (appel / visite) fixés par l'agent virtuel WhatsApp
     * OU par un commercial depuis le CRM.
     *
     * Connexion : 'temp' (base société).
     */
    public function up(): void
    {
        if (Schema::connection('temp')->hasTable('rendez_vous_agent')) {
            return;
        }

        Schema::connection('temp')->create('rendez_vous_agent', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('prospect_id')->index();
            $table->unsignedBigInteger('projet_id')->index();

            // NULL autorisé UNIQUEMENT pour une visite (RDV appel exige un commercial).
            $table->unsignedBigInteger('commercial_id')->nullable()->index();

            // 'appel' ou 'visite'
            $table->string('type', 20)->index();
            // 1 = appel, 2 = visite (miroir rapide de `type`)
            $table->unsignedTinyInteger('type_rdv')->index();

            $table->dateTime('date_rdv')->nullable();
            $table->string('creneau_label', 255)->nullable();

            // 'agent_virtuel' ou 'commercial'
            $table->string('source', 30)->default('agent_virtuel')->index();

            $table->text('commentaire')->nullable();

            // confirme | annule | reporte | termine
            $table->string('status', 20)->default('confirme')->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['prospect_id', 'type', 'status'], 'rdv_prospect_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('temp')->dropIfExists('rendez_vous_agent');
    }
};
