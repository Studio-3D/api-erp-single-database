<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TraitementAppel extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $with=['appel','user'];

    protected $table='traitements_appels';
    protected $dates=['deleted_at'];

    public function appel(){
        return $this->belongsTo(Appel::class,'appel_id');
    }
    public function user(){
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }
    public function reservation(){
        return $this->belongsTo(Reservation::class,'reservation_id');
    }
    public function visite(){
        return $this->belongsTo(Visite::class,'visite_id');
    }
    public function frein()
    {
        return $this->hasone(Frein::class,'traite_appel_id','id')->orderby('created_at','desc');
    }
    public function relance()
    {
        return $this->hasone(Relance_Rdv_Appel::class,'traite_appel_id','id')->orderby('created_at','desc')->where('date_relance','!=',null)->latest();
    }

    public function rdv()
    {
        return $this->hasone(Relance_Rdv_Appel::class,'traite_appel_id','id')->orderby('created_at','desc')->where('rdv','!=',null)->latest();
    }
    public function tranche(){
        return $this->belongsTo(Tranche::class,'tranche_id');
    }
    public function bloc(){
        return $this->belongsTo(Bloc::class,'bloc_id');
    }
    public function immeuble(){
        return $this->belongsTo(Immeuble::class,'immeuble_id');
    }
    public function type_biens(){
        return $this->hasMany(TypeBienAppel::class,'traite_appel_id');
    }



}
