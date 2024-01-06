<?php

use App\Enum\Civilite;
use App\Enum\SituationFamilliale;
use App\Enum\TypeClient;
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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->enum('type_client',[TypeClient::PARTICULIER->name,TypeClient::SOCIETE->name]);
            $table->string('nom');
            $table->string('prenom');
            $table->string('telephone_num1');
            $table->string('telephone_num2')->nullable();
            $table->boolean('notifie')->default(false);
            $table->string('email')->nullable();
            $table->enum('civilite',[Civilite::MR->name,Civilite::MME->name,Civilite::MLLE->name]);
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('pays')->nullable();
            $table->string('profession')->nullable();
            $table->string('cin')->unique();
            $table->string('lieu_naissance')->nullable();
            $table->string('nationalite')->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('nom_mari')->nullable();
            $table->string('lieu_mariage')->nullable();
            $table->date('date_mariage')->nullable();
            $table->string('nom_responsable')->nullable();
            $table->string('relation_familliale')->nullable();
            $table->enum('situation_familliale',[SituationFamilliale::CELEBATAIRE->name,SituationFamilliale::MARIE->name,SituationFamilliale::DIVORCE->name,SituationFamilliale::VEUF->name]);
            $table->string('nom_pere')->nullable();
            $table->string('nom_mere')->nullable();
            $table->foreignId('societe_id')->constrained('partenaires')->nullable()->onDelete('cascade');
            $table->foreignId('prospect_id')->constrained('prospects')->nullable()->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client');
    }
};
