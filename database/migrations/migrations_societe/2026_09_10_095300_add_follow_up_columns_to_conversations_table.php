<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('conversations', function (Blueprint $table) {
            // ✅ Timestamp du dernier message du client
            $table->timestamp('last_client_message_at')->nullable()->after('updated_at');

            // ✅ Timestamp de la dernière réponse du bot
            $table->timestamp('last_bot_message_at')->nullable()->after('last_client_message_at');

            // ✅ Flag pour savoir si un follow-up est programmé
            $table->boolean('follow_up_scheduled')->default(false)->after('last_bot_message_at');

            // ✅ Timestamp quand le follow-up a été envoyé
            $table->timestamp('follow_up_sent_at')->nullable()->after('follow_up_scheduled');

            // ✅ Numéro de téléphone (pour WhatsApp)
            $table->string('phone_number')->nullable()->after('session_id');

            // ✅ ID du projet
            $table->unsignedBigInteger('projet_id')->nullable()->after('phone_number');

            // ✅ ID du prospect
            $table->unsignedBigInteger('prospect_id')->nullable()->after('projet_id');

            // ✅ Index pour la recherche
            $table->index(['follow_up_scheduled', 'last_bot_message_at']);
        });
    }

    public function down()
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn([
                'last_client_message_at',
                'last_bot_message_at',
                'follow_up_scheduled',
                'follow_up_sent_at',
                'phone_number',
                'projet_id',
                'prospect_id'
            ]);
        });
    }
};
