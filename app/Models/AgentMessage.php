<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentMessage extends Model
{
    use SoftDeletes;

    protected $table = 'agent_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'metadata',
        'read_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'read_at' => 'datetime'
    ];

    protected $dates = [
        'read_at',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    // ========== RELATIONS ==========

    /**
     * Relation avec la conversation
     */
    public function conversation()
    {
        return $this->belongsTo(AgentConversation::class);
    }

    // ========== MÉTHODES ==========

    /**
     * Vérifier si le message est de l'utilisateur
     */
    public function isUser()
    {
        return $this->role === 'user';
    }

    /**
     * Vérifier si le message est de l'assistant
     */
    public function isAssistant()
    {
        return $this->role === 'assistant';
    }

    /**
     * Vérifier si le message est système
     */
    public function isSystem()
    {
        return $this->role === 'system';
    }

    /**
     * Marquer le message comme lu
     */
    public function markAsRead()
    {
        return $this->update(['read_at' => now()]);
    }

    /**
     * Vérifier si le message a été lu
     */
    public function isRead()
    {
        return $this->read_at !== null;
    }

    /**
     * Récupérer le nom du rôle en français
     */
    public function getRoleLabelAttribute()
    {
        return [
            'user' => 'Utilisateur',
            'assistant' => 'Assistant',
            'system' => 'Système'
        ][$this->role] ?? $this->role;
    }

    /**
     * Formater le contenu
     */
    public function getFormattedContentAttribute()
    {
        return nl2br(e($this->content));
    }

    /**
     * Scope: Messages de l'utilisateur
     */
    public function scopeUser($query)
    {
        return $query->where('role', 'user');
    }

    /**
     * Scope: Messages de l'assistant
     */
    public function scopeAssistant($query)
    {
        return $query->where('role', 'assistant');
    }

    /**
     * Scope: Messages non lus
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope: Messages lus
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope: Messages récents
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
