<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Remboursement extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $with=['reservation','desistement'];

    protected $table='remboursements';
    protected $dates=['deleted_at'];


    public function reservation(){
        return $this->belongsTo(Reservation::class,'reservation_id');
    }
    public function desistement(){
        return $this->belongsTo(Desistement::class,'desistement_id');
    }
    public function dossier_transfert(){
        return $this->belongsTo(Reservation::class,'dossier_id_transfert');
    }



}
