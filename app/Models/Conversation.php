<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class Conversation extends Model
{
    protected $fillable = [
        'session_id',
        'state',
        'history',
        'user_ip',
        'user_agent',
        'expires_at',
    ];

    protected $casts = [
        'state' => 'array',
        'history' => 'array',
        'expires_at' => 'datetime',
    ];

    // Nettoyer les conversations expirées (plus de 24h)
    public static function cleanExpired(): void
    {
        self::where('expires_at', '<', now())->delete();
    }

    // Créer ou récupérer une conversation
    public static function getOrCreate(string $sessionId, array $defaults = []): self
    {
        return self::firstOrCreate(
            ['session_id' => $sessionId],
            array_merge([
                'state' => [],
                'history' => [],
                'expires_at' => now()->addDay(),
            ], $defaults)
        );
    }

    // Mettre à jour la conversation
    public function updateConversation(array $state, array $history): self
    {
        $this->update([
            'state' => $state,
            'history' => $history,
            'expires_at' => now()->addDay(), // Prolonger l'expiration
        ]);

        return $this;
    }
}
