<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prospect extends Model
{
    use HasFactory;
    use SoftDeletes;
    /**
     * table associer a le model
     */
    protected $table='prospects';
    protected $dates=['deleted_at'];
    protected $with=['source','partenaire','affecte_par_admin','traite_par_user','commercial_affecte'];

    protected $fillable = [
        'cin', 'nom', 'prenom', 'telephone', 'telephone_num2', 'email',
        'origin', 'notifie', 'source', 'partenaire_id', 'message', 'ville',
        'projet_id','commercial_affecte' , 'affecte_par_admin_id',
        'traite_par_user_id', 'date_affectation', 'date_traitement'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class,'prospect_id');
    }
    public function visites_perdu()
    {
        return $this->hasMany(Visite::class,'prospect_id')->where('interet',3)->where('etat',1);
    }
    public function source(){
        return $this->belongsTo(Source::class,'source');
    }
    public function partenaire(){
        return $this->belongsTo(Partenaire::class,'partenaire_id');
    }

    public function visite_pre_reserves()
    {
        return $this->hasMany(Visite::class,'prospect_id')->where('interet',1)->where('etat',1)->where('statut',1);
    }

    public function visites()
    {
        return $this->hasMany(Visite::class,'prospect_id')->orderby('created_at','asc');
    }
    public function visite_first()
{
    return $this->hasOne(Visite::class, 'prospect_id')->orderBy('created_at', 'asc');
}

    public function appels()
    {
        return $this->hasOne(Appel::class,'prospect_id')->latest();

    }
 public function all_appels()
    {
        return $this->hasMany(Appel::class,'prospect_id');

    }
    public function last_statut()
    {
        return $this->hasOne(StatutProspect::class,'prospect_id')->latest();
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
}
