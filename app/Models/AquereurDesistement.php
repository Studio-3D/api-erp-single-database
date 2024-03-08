<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AquereurDesistement extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $with=['desistement','aquereur'];

    protected $table='aquereurs_desistements';
    protected $dates=['deleted_at'];


    public function desistement(){
        return $this->belongsTo(Desistement::class,'desistement_id');
    }
    public function aquereur(){
        return $this->belongsTo(Aquereur::class,'aq_id')->withTrashed();
    }
    public function client(){
        return $this->belongsTo(Client::class,'client_id');
    }

}
