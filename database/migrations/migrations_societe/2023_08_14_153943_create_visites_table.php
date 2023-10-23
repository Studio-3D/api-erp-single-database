<?php

use App\Enum\InteretEnum;
use App\Enum\StatutVisiteEnum;
use App\Enum\TypeNotificationEnum;
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
            $table->boolean('notifie')->default(false)->nullable();
            $table->enum('type_notification',[TypeNotificationEnum::SMS->name,TypeNotificationEnum::WHATSAPP->name,TypeNotificationEnum::APPEL->name,TypeNotificationEnum::EMAIL->name])->nullable();
            $table->enum('interet',[InteretEnum::INTERESSE->name,InteretEnum::RECEPCTIF->name,InteretEnum::PERDU->name]);
            $table->enum('mode_relance',[TypeNotificationEnum::SMS->name,TypeNotificationEnum::APPEL->name,TypeNotificationEnum::EMAIL->name])->nullable();
            $table->date('date_relance')->nullable();
            $table->enum('statut',[StatutVisiteEnum::PRE_RESERVE->name,StatutVisiteEnum::VENDU->name,StatutVisiteEnum::PRE_RESERVATION_PERDU->name])->nullable();
            $table->dateTime('rdv')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('sources')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('prospect_id')->constrained('prospects')->onDelete('cascade');
            $table->foreignId('bien_id')->nullable()->constrained('biens')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes(); // si on veut de garder historique de la visite.
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
