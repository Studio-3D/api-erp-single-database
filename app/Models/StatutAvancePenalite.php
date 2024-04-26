<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatutAvancePenalite extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='statut_avances_penalites';
    protected $with = ['avance','penalite'];
    protected $dates=['deleted_at'];

    public function avance(){
        return $this->belongsTo(Avance::class,'avance_id');
    }
    public function penalite()
    {
        return $this->belongsTo(PenaliteDesistement::class,'penalite_id');
    }

}
