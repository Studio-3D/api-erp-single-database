<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Encaissement extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'encaissements';

    protected $dates = ['deleted_at'];
    protected $with = ['avance'];

    public function user()
    {
        return $this->belongsTo(User::class ,'user_id_valider')->withTrashed();
    }

    public function avance()
    {
        return $this->belongsTo(Avance::class,'avance_id');
    }
    public function reservations()
    {
        return $this->belongsTo(Reservation::class,'reservation_id');
    }
    public function remboursement()
    {
        return $this->belongsTo(Remboursement::class,'remboursement_id');
    }

    public function penalite()
    {
        return $this->belongsTo(PenaliteDesistement::class,'penalite_id');
    }
     public function bien()
    {
        return $this->belongsTo(Bien::class,'bien_id');
    }

}
