<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HistoriqueDesistement extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'historiques_desistements';

    protected $dates = ['deleted_at'];
    protected $with = ['desistement', 'reservation','bien'];

    public function desistement()
    {
        return $this->belongsTo(Desistement::class ,'desistement_id');
    }
    public function reservation()
    {
        return $this->belongsTo(Reservation::class,'reservation_id');
    }
    public function bien()
    {
        return $this->belongsTo(Bien::class,'bien_id');
    }
}
