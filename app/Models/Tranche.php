<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tranche extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'tranches';
    /* protected $fillable = [
        'nom',
        'projet_id', 'date_lancement',
        'date_livraison',
        'niveau_etages', 'nbre_blocs','nbre_immeubles',
        'nbre_biens'
    ]; */
    protected $dates = ['deleted_at'];

    protected $with=['projet'];

    public function projet()
    {
        return $this->belongsTo(Projet::class, 'projet_id');
    }

    public function bien()
    {
        return $this->hasMany(Bien::class);
    }
    public function immeuble()
    {
        return $this->hasMany(Immeuble::class);
    }
    public function bloc()
    {
        return $this->hasMany(Bloc::class);
    }
    public function  frein(){
        return $this->belongsToMany(Frein::class,'frein_tranches');
    }
    public function  Coefficient_tranche(){
        return $this->hasOne(Coefficient_tranche::class,'tranche_id')->latest();
    }
    public function  biens_tva(){
        return $this->hasMany(Bien_Tva::class);
    }

}


