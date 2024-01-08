<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HistoReservation extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'historique_reservations';

    protected $dates = ['deleted_at'];
    protected $with = ['user', 'avance'];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class ,'reservation_id');
    }

    public function bien()
    {
        return $this->belongsTo(Bien::class,'bien_id');
    }
}
