<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Remboursement extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $with=['reservation','desistement','aquereur','banque'];

    protected $table='remboursements';
    protected $dates=['deleted_at'];


    public function reservation(){
        return $this->belongsTo(Reservation::class,'reservation_id');
    }
    public function aquereur(){
        return $this->belongsTo(Aquereur::class,'aquereur_id')->withTrashed();
    }
    public function desistement(){
        return $this->belongsTo(Desistement::class,'desistement_id');
    }
    public function dossier_transfert(){
        return $this->belongsTo(Reservation::class,'dossier_id_transfert');
    }
    public function banque(){
        return $this->belongsTo(Banque::class,'banque_id');
    }



}
