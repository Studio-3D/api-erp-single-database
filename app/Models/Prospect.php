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
    protected $with=['source','partenaire'];
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
}
