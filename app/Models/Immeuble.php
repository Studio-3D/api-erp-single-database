<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Immeuble extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'immeubles';
   protected $dates = ['deleted_at'];
   /* protected $fillable = [
    'nom',
    'projet_id', 'bloc_id',
    'tranche_id',
    'titre_foncier',
    'nbre_biens'
]; */

    protected $with=['projet','tranche','bloc'];


   public function projet()
   {
       return $this->belongsTo(Projet::class,  'projet_id');
   }

   public function tranche()
    {
        return $this->belongsTo(Tranche::class, 'tranche_id');
    }

   public function bloc()
   {
       return $this->belongsTo(Bloc::class, 'bloc_id');
   }
   public function bien()
   {
       return $this->hasMany(Bien::class);
   }
}
