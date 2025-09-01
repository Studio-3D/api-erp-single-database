<?php

use App\Enum\InteretEnum;
use App\Enum\StatutVisiteEnum;
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
        Schema::create('visites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('origin_id')->nullable(); // pour garder l'historique de visite
            $table->string('commentaire')->nullable(); // car en peut recoit des vistes sans commentaire.
            $table->enum('interet',[InteretEnum::Intéressé->value,InteretEnum::Réceptif->value,InteretEnum::Perdu->value])->comment('1=>interesse 2=>recpetif 3=>perdu 4=>injoignable');
            $table->enum('statut',[StatutVisiteEnum::Pré_Réservation->value,StatutVisiteEnum::Vendu->value,StatutVisiteEnum::Pré_Réservation_Perdu->value,StatutVisiteEnum::Réservation_Perdu->value,StatutVisiteEnum::Pré_Réservation_Vendu->value])->nullable()->comment('1=>Pre reservation 2=>Vendu 3=>Pre reservation perdu 4=> reservation perdu  5=>Pré_Réservation_Vendu');
            $table->boolean('etat')->default(true)->nullable();
            $table->foreignId('old_v_id')->nullable();
            $table->string('description')->nullable();
            $table->string('show')->nullable();
            $table->string('related_show_id')->nullable();
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('prospect_id')->constrained('prospects')->onDelete('cascade');
            $table->foreignId('bien_id')->nullable()->constrained('biens')->onDelete('cascade');
            $table->json('historique_modification')->nullable();
            $table->timestamps();
            $table->softDeletes(); // si on veut de garder historique de la visite.
            $table->index(['projet_id','etat','origin_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visites');
    }
};
