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
    /* protected $fillable = [
        'propriete_dite_bien',
        'numero', 'niveau',
        'orientation',
        'conventionne',
        'prix_unitaire','prix','c','superficie_habitable','nbre_facades','superficie_architecte',
        'superficie_parking','superficie_box','superficie_terrasse',
        'superficie_jardin','titre_foncier','etat','type_id',
        'projet_id','tranche_id','bloc_id','immeuble_id'
    ]; */
    protected $dates = ['deleted_at'];


    protected $with=['typeBien','projet','tranche','bloc','immeuble'];
    public function typeBien()
    {
        return $this->belongsTo(TypeBien::class, 'type_id','id');
    }
    public function projet()
    {
        return $this->belongsTo(Projet::class, 'projet_id','id');
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
        return $this->belongsTo(Immeuble::class, 'immeuble_id','id');
    }
    
}
