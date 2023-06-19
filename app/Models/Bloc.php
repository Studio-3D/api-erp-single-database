<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Bloc extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'blocs';
    protected $fillable = [
        'nom',
        'projet_id', 'titre_foncier',
        'tranche_id','nbre_immeubles',
        'nbre_biens'
    ];
    protected $dates = ['deleted_at'];
    
    public function projet()
    {
        return $this->belongsTo(Projet::class, 'projet_id');
    }
    public function tranche()
    {
        return $this->belongsTo(Tranche::class, 'tranche_id');
    }
}
