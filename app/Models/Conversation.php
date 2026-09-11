<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Conversation extends Model
{
    protected $fillable = [
        'session_id',
        'phone_number',
        'projet_id',
        'prospect_id',
        'state',
        'history',
        'user_ip',
        'user_agent',
        'expires_at',
        'last_client_message_at',
        'last_bot_message_at',
        'follow_up_scheduled',
        'follow_up_sent_at',
    ];

    protected $casts = [
        'state' => 'array',
        'history' => 'array',
        'expires_at' => 'datetime',
        'last_client_message_at' => 'datetime',
        'last_bot_message_at' => 'datetime',
        'follow_up_sent_at' => 'datetime',
        'follow_up_scheduled' => 'boolean',
    ];

    /**
     * Obtenir ou créer une conversation
     */
    public static function getOrCreate(string $sessionId, array $metadata = [])
    {
        $sessionId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sessionId);

        $conversation = self::where('session_id', $sessionId)->first();

        if (!$conversation) {
            $conversation = self::create([
                'session_id' => $sessionId,
                'phone_number' => $metadata['phone_number'] ?? null,
                'projet_id' => $metadata['projet_id'] ?? null,
                'prospect_id' => $metadata['prospect_id'] ?? null,
                'state' => [],
                'history' => [],
                'user_ip' => $metadata['user_ip'] ?? null,
                'user_agent' => $metadata['user_agent'] ?? null,
                'expires_at' => now()->addDays(7),
            ]);
            Log::info('Nouvelle conversation créée', ['session_id' => $sessionId]);
        } else {
            // ✅ Mettre à jour les métadonnées si elles sont fournies
            if (!empty($metadata['phone_number'])) $conversation->phone_number = $metadata['phone_number'];
            if (!empty($metadata['projet_id'])) $conversation->projet_id = $metadata['projet_id'];
            if (!empty($metadata['prospect_id'])) $conversation->prospect_id = $metadata['prospect_id'];
            $conversation->save();
        }

        return $conversation;
    }

    /**
     * Mettre à jour la conversation
     */
    public function updateConversation(array $state, array $history): void
    {
        $this->state = $state;
        $this->history = $history;
        $this->expires_at = now()->addDays(7);
        $this->save();

        Log::info('Conversation mise à jour', [
            'session_id' => $this->session_id,
            'id' => $this->id,
            'history_count' => count($history),
        ]);
    }

    /**
     * 🔥 MARQUER QU'UN MESSAGE CLIENT A ÉTÉ REÇU
     */
    public function markClientMessage(): void
    {
        $this->last_client_message_at = now();
        // ❌ Annuler le follow-up car le client a répondu
        $this->follow_up_scheduled = false;
        $this->follow_up_sent_at = null;
        $this->save();

        Log::info('📩 Message client marqué', [
            'session_id' => $this->session_id,
        ]);
    }

    /**
     * 🔥 MARQUER QU'UN MESSAGE BOT A ÉTÉ ENVOYÉ + PROGRAMMER LE FOLLOW-UP
     */
    public function markBotMessage(): void
    {
        $this->last_bot_message_at = now();
        $this->follow_up_scheduled = true;
        $this->follow_up_sent_at = null;
        $this->save();

        Log::info('🤖 Message bot marqué + follow-up programmé', [
            'session_id' => $this->session_id,
        ]);
    }

    /**
     * 🔥 RÉCUPÉRER LES CONVERSATIONS À RELANCER
     * (1h après le dernier message du bot, sans réponse du client)
     */
    public static function getConversationsToFollowUp(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('follow_up_scheduled', true)
            ->whereNotNull('last_bot_message_at')
            ->where('last_bot_message_at', '<=', now()->subHour())
            ->whereNull('follow_up_sent_at')
            ->where(function ($query) {
                // ✅ Soit pas de message client
                // ✅ Soit le dernier message client est AVANT le dernier message bot
                $query->whereNull('last_client_message_at')
                    ->orWhereColumn('last_client_message_at', '<', 'last_bot_message_at');
            })
            ->get();
    }

    /**
     * 🔥 MARQUER LE FOLLOW-UP COMME ENVOYÉ
     */
    public function markFollowUpSent(): void
    {
        $this->follow_up_sent_at = now();
        $this->follow_up_scheduled = false;
        $this->save();

        Log::info('✅ Follow-up marqué comme envoyé', [
            'session_id' => $this->session_id,
        ]);
    }

    /**
     * Nettoyer les conversations expirées
     */
    public static function cleanExpired(): int
    {
        $count = self::where('expires_at', '<', now())->delete();
        Log::info('Conversations expirées nettoyées', ['count' => $count]);
        return $count;
    }

    /**
     * Supprimer une conversation
     */
    public static function deleteBySessionId(string $sessionId): bool
    {
        $sessionId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sessionId);

        if (empty($sessionId)) {
            return false;
        }

        $deleted = self::where('session_id', $sessionId)->delete();

        Log::info('Conversation supprimée', [
            'session_id' => $sessionId,
            'deleted' => $deleted
        ]);

        return $deleted > 0;
    }
}
