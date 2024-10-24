<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bien extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'biens';
    protected $dates = ['deleted_at'];

    protected $with = ['typeBien', 'projet', 'tranche', 'bloc', 'immeuble','typologie','vue','compositionBien'];
    public function typeBien()
    {
        return $this->belongsTo(TypeBien::class, 'type_id');
    }
    public function projet()
    {
        return $this->belongsTo(Projet::class, 'projet_id');
    }
    public function tranche()
    {
        return $this->belongsTo(Tranche::class, 'tranche_id');
    }
    public function bloc()
    {
        return $this->belongsTo(Bloc::class, 'bloc_id');
    }
    public function immeuble()
    {
        return $this->belongsTo(Immeuble::class, 'immeuble_id');
    }

    public function vue()
    {
        return $this->belongsTo(Vue::class, 'vue_id');
    }

    public function typologie()
    {
        return $this->belongsTo(Typologie::class, 'typologie_id');
    }
    public function desistement()
    {
        return $this->belongsTo(Desistement::class, 'desistement_id');
    }

    public function is_proposed()
    {
        return $this->hasone(Proposition::class,'bien_id')->orderby('created_at','desc')->latest();
    }
    public function historique_bien_pre_reserve()
    {
        return $this->hasone(HistoriqueBien::class,'bien_id')->where('action',5)->orderby('created_at','desc')->latest();
    }
    public function last_pre_reservation()
    {
        return $this->hasone(PreReservation::class,'bien_id')->orderby('date_pre_reserve','desc')->latest();
    }
    public function reservation()
    {
        return $this->hasone(Reservation::class,'bien_id')->orderby('created_at','desc')->where('etat',1)->latest();
    }
    public function compositionBien()
   {
       return $this->hasMany(CompositionBien::class);
   }
   public function Bien_tva()
   {
       return $this->hasone(Bien_tva::class,'bien_id')->latest();
   }
   public function tva_collectes()
   {
       return $this->hasMany(TvaCollecte::class,'bien_id')->where('etat',1);
   }
   public function tva_collectes_ancien_reservation()
   {
       return $this->hasMany(TvaCollecte::class,'bien_id')->where('etat',4);
   }
   public function tva_collectes_archive()
   {
       return $this->hasMany(TvaCollecte::class,'bien_id')->onlyTrashed();
   }
   public function encaissements()
   {
       return $this->hasMany(Encaissement::class,'bien_id');
   }
   public function remiseCle()
   {
       return $this->hasone(RemiseCle::class,'bien_id')->orderby('created_at','desc')->latest();
   }

}
