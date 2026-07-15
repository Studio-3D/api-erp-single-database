<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class HistoriqueRelanceWhatsapp extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historique_relances_whatsapp';

    protected $fillable = [
        'projet_id',
        'user_id',
        'prospect_ids',
        'message',
        'file',
        'media_url',
        'scheduled_date',
        'sent_date',
        'status',
        'response',
        'error_message',
        'metadata',
        'statistics', // Add this if you have the column
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'sent_date' => 'datetime',
        'prospect_ids' => 'array',
        'metadata' => 'array',
        'statistics' => 'array', // Add this if you have the column
    ];

    // Relationships
    public function projet()
    {
        return $this->belongsTo(Projet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ✅ Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    // ✅ Status Methods
    public function markAsProcessing()
    {
        $this->update([
            'status' => 'processing',
        ]);
    }

    public function markAsSent($response = null)
    {
        $this->update([
            'status' => 'sent',
            'sent_date' => now(),
            'response' => $response,
        ]);
    }

    public function markAsFailed($errorMessage = null)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    public function markAsCancelled()
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    // ✅ Accessors
    public function getFileUrlAttribute()
    {
        if ($this->file) {
            return asset('storage/relance_whatsapp_message/' . $this->file);
        }
        return null;
    }





    public function getTotalProspectsAttribute()
    {
        return $this->prospect_ids ? count($this->prospect_ids) : 0;
    }

    public function getSentCountAttribute()
    {
        if ($this->statistics && isset($this->statistics['sent_count'])) {
            return $this->statistics['sent_count'];
        }
        return 0;
    }

    public function getFailedCountAttribute()
    {
        if ($this->statistics && isset($this->statistics['failed_count'])) {
            return $this->statistics['failed_count'];
        }
        return 0;
    }

    public function getSentNumbersAttribute()
    {
        if ($this->statistics && isset($this->statistics['sent_numbers'])) {
            return $this->statistics['sent_numbers'];
        }
        return [];
    }

    public function getFailedNumbersAttribute()
    {
        if ($this->statistics && isset($this->statistics['failed_numbers'])) {
            return $this->statistics['failed_numbers'];
        }
        return [];
    }

    public function getResultsAttribute()
    {
        if ($this->statistics && isset($this->statistics['results'])) {
            return $this->statistics['results'];
        }
        return [];
    }

    // ✅ Utility method to get status with emoji
    public function getStatusWithEmojiAttribute()
    {
        return $this->status_label;
    }
    // In HistoriqueRelanceWhatsapp model
public function getStatusLabelAttribute()
{
    $labels = [
        'pending' => '⏳ En attente',
        'sent' => '✅ Envoyé',
        'failed' => '❌ Échoué',
        'cancelled' => '🚫 Annulé',
        'processing' => '🔄 En cours',
        'partial' => '⚠️ Partiellement envoyé', // ✅ ADD THIS
    ];
    return $labels[$this->status] ?? $this->status;
}

public function getStatusColorAttribute()
{
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'sent' => 'bg-green-100 text-green-800',
        'failed' => 'bg-red-100 text-red-800',
        'cancelled' => 'bg-gray-100 text-gray-800',
        'processing' => 'bg-blue-100 text-blue-800',
        'partial' => 'bg-orange-100 text-orange-800', // ✅ ADD THIS
    ];
    return $colors[$this->status] ?? 'bg-gray-100 text-gray-800';
}

/**
 * Get prospects for this history
 */
public function getProspectsAttribute()
{
    if ($this->prospect_ids) {
        $ids = is_string($this->prospect_ids)
            ? json_decode($this->prospect_ids, true)
            : $this->prospect_ids;

        if (is_array($ids) && !empty($ids)) {
            return Prospect::on('temp')
                ->whereIn('id', $ids)
                ->select('id', 'nom', 'prenom', 'telephone', 'telephone_num2', 'email')
                ->get();
        }
    }
    return collect();
}

/**
 * Get statistics as array
 */
public function getStatisticsArrayAttribute()
{
    $stats = $this->statistics ?? [];
    if (is_string($stats)) {
        $stats = json_decode($stats, true);
    }
    return $stats;
}

/**
 * Get metadata as array
 */
public function getMetadataArrayAttribute()
{
    $meta = $this->metadata ?? [];
    if (is_string($meta)) {
        $meta = json_decode($meta, true);
    }
    return $meta;
}

/**
 * Get response as array
 */
public function getResponseArrayAttribute()
{
    $response = $this->response ?? [];
    if (is_string($response)) {
        $response = json_decode($response, true);
    }
    return $response;
}
}
