<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PenaliteDesistement extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $with=['desistement','Piece_jointes'];

    protected $table='penalites_desistements';
    protected $dates=['deleted_at'];

    public function desistement(){
        return $this->belongsTo(Desistement::class,'desistement_id');
    }
    public function Piece_jointes()
    {
        return $this->hasMany(PiecesJointe::class,'penalite_id')->where('active',1);
    }
    public function banque()
    {
        return $this->belongsTo(Banque::class,'banque_id');
    }
    public function responsable_validation()
    {
        return $this->belongsTo(User::class,'user_id_valider');
    }
    public function last_statut()
    {
        return $this->hasOne(StatutAvancePenalite::class,'penalite_id')->latest();
    }

}
