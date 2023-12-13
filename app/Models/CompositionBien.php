<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class CompositionBien extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'composition_biens';
    /* protected $fillable = [
        'bien_id',
        'nbre_chambres', 'nbre_salons',
        'nbre_sdb','nbre_cuisines',
        'nbre_halls','nbre_terasses','nbre_balcons','nbre_buanderies',
        'nbre_placards','nbre_receptions'
    ]; */
   
    protected $dates = ['deleted_at'];
    public function bien()
    {
        return $this->belongsTo(Bien::class,'bien_id');
    }
}
