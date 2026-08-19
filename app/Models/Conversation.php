<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Conversation extends Model
{
    protected $fillable = [
        'session_id',
        'state',
        'history',
        'user_ip',
        'user_agent',
        'expires_at'
    ];

    protected $casts = [
        'state' => 'array',
        'history' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Obtenir ou créer une conversation
     */
    public static function getOrCreate(string $sessionId, array $metadata = [])
    {
        // Nettoyer le session_id
        $sessionId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sessionId);

        $conversation = self::where('session_id', $sessionId)->first();

        if (!$conversation) {
            $conversation = self::create([
                'session_id' => $sessionId,
                'state' => [],
                'history' => [],
                'user_ip' => $metadata['user_ip'] ?? null,
                'user_agent' => $metadata['user_agent'] ?? null,
                'expires_at' => now()->addDays(7),
            ]);
            Log::info('Nouvelle conversation créée', ['session_id' => $sessionId]);
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
     * Nettoyer les conversations expirées
     */
    public static function cleanExpired(): int
    {
        $count = self::where('expires_at', '<', now())->delete();
        Log::info('Conversations expirées nettoyées', ['count' => $count]);
        return $count;
    }

    /**
     * Supprimer une conversation (méthode helper)
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
