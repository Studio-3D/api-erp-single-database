<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NouvelAquereurDesistement extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table='nouvel_aquereurs_desistements';
    protected $dates=['deleted_at'];
    protected $with=['desistement'];

    public function desistement(){
        return $this->belongsTo(Desistement::class,'desistement_id');
    }

}
