<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectFusion extends Model
{
    use SoftDeletes;

    protected $table = 'prospect_fusions';

    protected $fillable = [
        'prospect_principal_id',
        'prospect_fusion_id',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    protected $with = ['prospectFusionne', 'prospectPrincipal'];

    // =====================================================
    // ⭐ RELATIONS
    // =====================================================

    /**
     * Prospect principal (Malika)
     */
    public function prospectPrincipal(): BelongsTo
    {
        return $this->belongsTo(Prospect::class, 'prospect_principal_id');
    }

    /**
     * Prospect fusionné (Omar)
     */
    public function prospectFusionne(): BelongsTo
    {
        return $this->belongsTo(Prospect::class, 'prospect_fusion_id');
    }

    /**
     * User li dar fusion
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // =====================================================
    // ⭐ VALIDATION (9bel l-create)
    // =====================================================

    protected static function booted()
    {
        static::creating(function ($fusion) {
            $fusion->validateFusion();
        });
    }

    /**
     * Validation logic
     */
    public function validateFusion(): void
    {
        $principalId = $this->prospect_principal_id;
        $fusionId = $this->prospect_fusion_id;

        // 1. Ma ymkench ykoun principal = fusionné
        if ($principalId === $fusionId) {
            throw new \Exception(
                'Un prospect ne peut pas être fusionné avec lui-même'
            );
        }

        // 2. Cheki wach prospect_fusion_id déjà mdemj
        $dejaFusionne = self::where(function ($q) use ($fusionId) {
                $q->where('prospect_fusion_id', $fusionId)
                  ->orWhere('prospect_principal_id', $fusionId);
            })
            ->exists();

        if ($dejaFusionne) {
            throw new \Exception(
                'Ce prospect est déjà fusionné avec un autre prospect'
            );
        }

        // 3. Cheki wach prospect_principal_id mdemj
        $principalDejaFusionne = self::where('prospect_fusion_id', $principalId)
            ->exists();

        if ($principalDejaFusionne) {
            throw new \Exception(
                'Le prospect principal est déjà fusionné avec un autre prospect'
            );
        }
    }

    // =====================================================
    // ⭐ SCOPES
    // =====================================================

    /**
     * Fusions li had prospect howa principal fihom
     */
    public function scopeWherePrincipal($query, $prospectId)
    {
        return $query->where('prospect_principal_id', $prospectId);
    }

    /**
     * Fusions li had prospect howa fusionné fihom
     */
    public function scopeWhereFusionne($query, $prospectId)
    {
        return $query->where('prospect_fusion_id', $prospectId);
    }
}
