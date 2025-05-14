<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enum\StatutProspectEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('statut_prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained('prospects')->onDelete('cascade');
            $table->enum('statut',[StatutProspectEnum::Planification_rdv->value,StatutProspectEnum::Injoignable->value,StatutProspectEnum::Rappel->value,StatutProspectEnum::Convert_visite->value,StatutProspectEnum::nb_appel->value])->comment('1=>Planification_rdv 2=>Injoignable 3=>Rappel 4=>visite 5==>store un nv appel');
            $table->foreignId('user_id_traite')->constrained('users')->onDelete('cascade');
            $table->date('date_traitement')->nullable();
            $table->dateTime('rdv')->nullable();
            $table->date('date_rappel')->nullable();
            $table->string('commentaire')->nullable();
            $table->foreignId('visite_id')->nullable()->constrained('visites')->onDelete('cascade');
            $table->foreignId('appel_id')->nullable()->constrained('appels')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statut_prospects');
    }
};
