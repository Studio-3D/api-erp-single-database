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
    protected $with = ['user', 'bien'];
    //pour repmir les json par array
    protected $casts = [
        'description' => 'array',
    ];
    public function reservation()
    {
        return $this->belongsTo(Reservation::class ,'reservation_id');
    }

    public function bien()
    {
        return $this->belongsTo(Bien::class,'bien_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class ,'user_id')->withTrashed();
    }
}
