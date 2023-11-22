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
    protected $casts=['rdv'=>'datetime:Y-m-d\TH:i'];
    protected $with=['prospect','bien','source','user','historique_bien_visite','partenaire'];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function prospect()
    {
        return $this->belongsTo(Prospect::class,'prospect_id');
    }
    public function bien()
    {
        return $this->belongsTo(Bien::class,'bien_id');
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
   /* public function historiques()
    {
        return $this->hasMany(HistoriqueVisite::class,'visite_id')->orderby('created_at','asc');
    }*/

}
