<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatutReservation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='statut_reservations';
    protected $with = ['reservation','user'];
    protected $dates=['deleted_at'];

    public function reservation(){
        return $this->belongsTo(Reservation::class,'reservation_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,'user_id_valider')->withTrashed();
    }

}
