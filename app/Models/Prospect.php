<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;   // ⭐ ZID HADA
use Illuminate\Database\Eloquent\Relations\HasOne;    // ⭐ ZID HADA

class Prospect extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'prospects';
    protected $dates = ['deleted_at'];
    protected $with = ['source', 'partenaire', 'affecte_par_admin', 'traite_par_user', 'commercial_affecte'];
    protected $appends = ['visites_count', 'appels_count'];

    protected $fillable = [
        'cin', 'nom', 'prenom', 'telephone', 'telephone_num2', 'email',
        'origin', 'notifie', 'source', 'partenaire_id', 'message', 'ville',
        'projet_id', 'commercial_affecte', 'affecte_par_admin_id',
        'traite_par_user_id', 'date_affectation', 'date_traitement'
    ];

    // =====================================================
    // ⭐ RELATIONS EXISTANTES
    // =====================================================

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function visites_perdu()
    {
        return $this->hasMany(Visite::class, 'prospect_id')->where('interet', 3)->where('etat', 1);
    }

    public function source()
    {
        return $this->belongsTo(Source::class, 'source');
    }

    public function partenaire()
    {
        return $this->belongsTo(Partenaire::class, 'partenaire_id');
    }

    public function visite_pre_reserves()
    {
        return $this->hasMany(Visite::class, 'prospect_id')->where('interet', 1)->where('etat', 1)->where('statut', 1);
    }

    public function visites()
    {
        return $this->hasMany(Visite::class, 'prospect_id')->orderby('created_at', 'asc');
    }

    public function visite_first()
    {
        return $this->hasOne(Visite::class, 'prospect_id')->orderBy('created_at', 'asc');
    }

    public function appels()
    {
        return $this->hasOne(Appel::class, 'prospect_id')->latest();
    }

    public function all_appels()
    {
        return $this->hasMany(Appel::class, 'prospect_id');
    }

    public function last_statut()
    {
        return $this->hasOne(StatutProspect::class, 'prospect_id')
            ->latest('created_at')
            ->latest('id');
    }

    public function commercial_affecte()
    {
        return $this->belongsTo(User::class, 'commercial_affecte');
    }

    public function affecte_par_admin()
    {
        return $this->belongsTo(User::class, 'affecte_par_admin_id');
    }

    public function traite_par_user()
    {
        return $this->belongsTo(User::class, 'traite_par_user_id');
    }

    // =====================================================
    // ⭐ ACCESSORS
    // =====================================================

    public function getVisitesCountAttribute()
    {
        return $this->visites()->count();
    }

    public function getAppelsCountAttribute()
    {
        return $this->appels()->count();
    }

    // =====================================================
    // ⭐ RELATIONS DE FUSION (NOUVELLES)
    // =====================================================

    /**
     * Fusions où ce prospect est le PRINCIPAL
     * (un prospect principal peut avoir plusieurs fusions)
     */
    public function fusionsCommePrincipal(): HasMany
    {
        return $this->hasMany(ProspectFusion::class, 'prospect_principal_id');
    }

    /**
     * Fusion où ce prospect est FUSIONNÉ
     * (un prospect fusionné n'appartient qu'à une seule fusion)
     */
    public function fusionCommeFusionne(): HasOne
    {
        return $this->hasOne(ProspectFusion::class, 'prospect_fusion_id');
    }

    // =====================================================
    // ⭐ ACCESSORS DE FUSION
    // =====================================================

    /**
     * Indique si ce prospect est déjà fusionné
     */
    public function getEstFusionneAttribute(): bool
    {
        return $this->fusionCommeFusionne()->exists();
    }

    /**
     * Indique si ce prospect a des fusions (est principal)
     */
    public function getAvecFusionsAttribute(): bool
    {
        return $this->fusionsCommePrincipal()->exists();
    }
}
