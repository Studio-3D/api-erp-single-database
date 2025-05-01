<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appel extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $with=['prospect','projet'];

    protected $table='appels';
    protected $dates=['deleted_at'];

    public function prospect(){
        return $this->belongsTo(Prospect::class,'prospect_id');
    }
    public function projet(){
        return $this->belongsTo(Projet::class,'projet_id');
    }

    public function last_traitement_appel()
    {
        return $this->hasOne(TraitementAppel::class,'appel_id')->latest();
    }
}
