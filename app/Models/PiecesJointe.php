<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PiecesJointe extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table='pieces_jointes';
    protected $dates=['deleted_at'];

    public function  reservation(){
        return $this->belongsTo(Reservation::class,'reservation_id');
    }

    public function avance(){
        return $this->belongsTo(Avance::class,'avance_id');
    }
}
