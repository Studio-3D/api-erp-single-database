<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentConversation extends Model
{
    use SoftDeletes;

    protected $table = 'agent_conversations';

    protected $fillable = [
        'session_id',
        'prospect_id',
        'user_id',
        'projet_id',
        'platform',
        'nom_agent',
        'context',
        'metadata',
        'is_active',
        'last_activity_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'context' => 'array',
        'last_activity_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    protected $dates = [
        'last_activity_at',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    // ========== RELATIONS ==========

    /**
     * Relation avec les messages
     */
    public function messages()
    {
        return $this->hasMany(AgentMessage::class);
    }

    /**
     * Relation avec le prospect
     */
    public function prospect()
    {
        return $this->belongsTo(Prospect::class);
    }

    /**
     * Relation avec l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec le projet
     */
    public function projet()
    {
        return $this->belongsTo(Projet::class);
    }

    // ========== MÉTHODES ==========

    /**
     * Ajouter un message à la conversation
     */
    public function addMessage($role, $content, $metadata = null)
    {
        return $this->messages()->create([
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata
        ]);
    }

    /**
     * Récupérer le dernier message
     */
    public function getLastMessage()
    {
        return $this->messages()->latest()->first();
    }

    /**
     * Récupérer les messages non lus
     */
    public function getUnreadMessages()
    {
        return $this->messages()->whereNull('read_at')->get();
    }

    /**
     * Marquer tous les messages comme lus
     */
    public function markAsRead()
    {
        return $this->messages()->whereNull('read_at')->update([
            'read_at' => now()
        ]);
    }

    /**
     * Récupérer le contexte
     */
    public function getContext()
    {
        return $this->context ?? [];
    }

    /**
     * Mettre à jour le contexte
     */
    public function updateContext($data)
    {
        $current = $this->getContext();
        return $this->update([
            'context' => array_merge($current, $data)
        ]);
    }

    /**
     * Vérifier si la conversation est active
     */
    public function isActive()
    {
        return $this->is_active;
    }

    /**
     * Activer la conversation
     */
    public function activate()
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Désactiver la conversation
     */
    public function deactivate()
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Mettre à jour la dernière activité
     */
    public function touchActivity()
    {
        return $this->update(['last_activity_at' => now()]);
    }

    /**
     * Scope: Conversations actives
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Par plateforme
     */
    public function scopePlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope: Par session
     */
    public function scopeSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Scope: Par projet
     */
    public function scopeProjet($query, $projetId)
    {
        return $query->where('projet_id', $projetId);
    }

    /**
     * Scope: Conversations récentes
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
