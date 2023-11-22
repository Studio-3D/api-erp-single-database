<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enum\InteretEnum;
use App\Enum\StatutVisiteEnum;
use App\Enum\TypeNotificationEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('historique_visites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('origin_id'); // pour garder l'historique de visite
            $table->string('commentaire')->nullable(); // car en peut recoit des vistes sans commentaire.
            $table->boolean('notifie')->default(false)->nullable();
            $table->integer('action');//1 modifiction /2 suppression
            $table->enum('interet',[InteretEnum::INTERESSE->value,InteretEnum::RECEPTIF->value,InteretEnum::PERDU->value]);
            $table->enum('mode_relance',[TypeNotificationEnum::SMS->value,TypeNotificationEnum::APPEL->value,TypeNotificationEnum::EMAIL->value])->nullable();
            $table->date('date_relance')->nullable();
            $table->enum('statut',[StatutVisiteEnum::PRE_RESERVATION->value,StatutVisiteEnum::VENDU->value])->nullable();
            $table->dateTime('rdv')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bien_id')->nullable()->constrained('biens')->onDelete('cascade');
            $table->foreignId('visite_id')->nullable()->constrained('visites')->onDelete('cascade');
            $table->double('frein_prix_min')->nullable();
            $table->double('frein_prix_max')->nullable();
            $table->double('frein_superficie_min')->nullable();
            $table->double('frein_superficie_max')->nullable();
            $table->boolean('liste_attente')->default(false);
            $table->double('avance')->nullable();
            $table->string('frein_tranches')->nullable(); //tranches separer par ;
            $table->string('frein_etages')->nullable();
            $table->string('frein_orientations')->nullable();
            $table->string('frein_typologies')->nullable();
            $table->string('frein_vues')->nullable();
            $table->timestamps();
            $table->softDeletes(); // si on veut de garder historique de la visite.
            $table->index(['visite_id','origin_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
