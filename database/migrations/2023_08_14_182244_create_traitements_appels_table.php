<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enum\InteretEnumAppel;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('traitements_appels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appel_id')->constrained('appels')->onDelete('cascade');
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->onDelete('cascade');
            $table->foreignId('visite_id')->nullable()->constrained('visites')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('type_appel')->comment('1 entrant /2 sortant');
            $table->dateTime('date');
            $table->enum('interet',[InteretEnumAppel::Intéressé->value,InteretEnumAppel::Réceptif->value,InteretEnumAppel::Perdu->value,InteretEnumAppel::Injoignable->value]);
            $table->integer('etat')->comment('0 recu /1 traite 2/injoignable');
            $table->dateTime('date_traitement')->nullable();
            $table->foreignId('tranche_id')->nullable()->constrained('tranches')->onDelete('cascade');
            $table->foreignId('bloc_id')->nullable()->constrained('blocs')->onDelete('cascade');
            $table->foreignId('immeuble_id')->nullable()->constrained('immeubles')->onDelete('cascade');
            $table->string('etage')->nullable();
            $table->string('orientation')->nullable();
            $table->dateTime('date_convert_visite')->nullable();
            $table->foreignId('user_id_convert_visite')->nullable()->constrained('users')->onDelete('cascade');
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
        Schema::dropIfExists('traitements_appels');
    }
};
