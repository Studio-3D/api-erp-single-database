<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enum\StatutRdvEnum;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->onDelete('cascade');
            $table->enum('statut',[StatutRdvEnum::En_Attente->value,StatutRdvEnum::traite->value,StatutRdvEnum::non_traite->value,StatutRdvEnum::annule_automatique->value])->comment('1=>en attente 2=>traite 3=>non traite 4=> annule automatique');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->dateTime('rdv')->nullable();
            $table->dateTime('prochaine_relance')->nullable();
            $table->json('relances_history')->nullable()->after('prochaine_relance')->comment('Historique des relances au format JSON');
            $table->dateTime('date_validation')->nullable();
            $table->foreignId('user_id_valider')->nullable()->constrained('users')->onDelete('cascade');
            $table->String('type')->nullable()->comment('1=>compromis de vente ,2=>contrat de vente 3=> bloque');
            $table->string('commentaire')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rendez_vous');

    }
};
