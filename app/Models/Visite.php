<?php

namespace App\Models;

use App\Enum\InteretEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visite extends Model
{
    use HasFactory;
    use SoftDeletes;
    /**
     * table associer a le model
     */
    protected $table='visites';
    protected $dates=['deleted_at'];
    protected $with=['prospect','bien','source','user','historique_bien_visite','partenaire'];
 //pour repmir les json par array
    protected $casts = [
        'historique_modication' => 'array',
    ];
    public function user()
    {
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }

    public function prospect()
    {
        return $this->belongsTo(Prospect::class,'prospect_id');
    }
    public function bien()
    {
        return $this->belongsTo(Bien::class,'bien_id');
    }
    public function projet()
    {
        return $this->belongsTo(Projet::class,'projet_id');
    }

    public function source(){
        return $this->belongsTo(Source::class,'source_id');
    }
    public function partenaire(){
        return $this->belongsTo(Partenaire::class,'partenaire_id');
    }
    public function historique_bien_visite()
    {
        return $this->hasone(HistoriqueBien::class,'bien_id','bien_id')->where('action',5)->orderby('created_at','desc')->latest();
    }
    public function pre_reservation_visite()
    {
        return $this->hasone(PreReservation::class,'bien_id','bien_id')->orderby('created_at','desc')->where('visite_id','!=',null)->latest();
    }
    public function relance_relation()
    {
        return $this->hasone(Relance_Rdv_Visite::class,'visite_id','id')->withTrashed()->orderby('created_at','desc')->where('date_relance','!=',null)->latest();
    }
    public function historique_relances_rdvs()
    {
        return $this->hasMany(Relance_Rdv_Visite::class,'visite_id','id')->withTrashed()->orderby('created_at','desc');
    }
    public function rdv_relation()
    {
        return $this->hasone(Relance_Rdv_Visite::class,'visite_id','id')->withTrashed()->orderby('created_at','desc')->where('rdv','!=',null)->latest();
    }
    public function reservation()
    {
        return $this->hasone(Reservation::class,'visite_id','id');
    }
    //frein used on cas de store n visite
    public function freins()
    {
        return $this->hasone(Frein::class,'visite_id')->where('etat','!=',5)->orderby('created_at','desc')->latest();
    }

    public function traitement_frein()
    {
        return $this->hasMany(TraitementFrein::class,'visite_id','id')->orderby('created_at','asc');
    }


}
