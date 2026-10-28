<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('conversations', function (Blueprint $table) {
            // ✅ Timestamp du dernier message envoyé par le client
            $table->timestamp('last_client_message_at')
                ->nullable()
                ->after('updated_at')
                ->comment('Date du dernier message envoyé par le client');

            // ✅ Timestamp de la dernière réponse du bot (agent virtuel)
            $table->timestamp('last_bot_message_at')
                ->nullable()
                ->after('last_client_message_at')
                ->comment('Date du dernier message envoyé par le bot (agent virtuel)');

            // ✅ Flag : une relance est-elle programmée ?
            $table->boolean('follow_up_scheduled')
                ->default(false)
                ->after('last_bot_message_at')
                ->comment('1=relance programmée, 0=aucune relance en attente');

            // ✅ Timestamp de la dernière relance envoyée
            $table->timestamp('follow_up_sent_at')
                ->nullable()
                ->after('follow_up_scheduled')
                ->comment('Date d\'envoi de la dernière relance');

            // ✅ Nombre de relances déjà envoyées (max 2)
            $table->tinyInteger('follow_up_count')
                ->unsigned()
                ->default(0)
                ->after('follow_up_sent_at')
                ->comment('Nombre de relances déjà envoyées (max 2)');

            // ✅ Numéro de téléphone WhatsApp du prospect
            $table->string('phone_number')
                ->nullable()
                ->after('session_id')
                ->comment('Numéro WhatsApp du prospect (format +212...)');

            // ✅ ID du projet associé
            $table->unsignedBigInteger('projet_id')
                ->nullable()
                ->after('phone_number')
                ->comment('ID du projet auquel la conversation est rattachée');

            // ✅ ID du prospect associé
            $table->unsignedBigInteger('prospect_id')
                ->nullable()
                ->after('projet_id')
                ->comment('ID du prospect dans la table prospects');

            // ✅ Index pour accélérer les requêtes des CRON
            $table->index(
                ['follow_up_scheduled', 'last_bot_message_at'],
                'idx_follow_up_scheduled_bot'
            );

            // ✅ Index pour les requêtes du CRON AssignAfterFollowUps
            $table->index(
                ['follow_up_count', 'follow_up_sent_at'],
                'idx_follow_up_count_sent'
            );

            // ✅ Index pour la recherche par prospect
            $table->index('prospect_id', 'idx_prospect_id');
        });
    }

    public function down()
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Supprimer les index d'abord
            $table->dropIndex('idx_follow_up_scheduled_bot');
            $table->dropIndex('idx_follow_up_count_sent');
            $table->dropIndex('idx_prospect_id');

            // Puis supprimer les colonnes
            $table->dropColumn([
                'last_client_message_at',
                'last_bot_message_at',
                'follow_up_scheduled',
                'follow_up_sent_at',
                'follow_up_count',
                'phone_number',
                'projet_id',
                'prospect_id',
            ]);
        });
    }
};
