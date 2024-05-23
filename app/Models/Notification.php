<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='notifications';
    protected $dates=['deleted_at'];

    public function visite(){
        return $this->belongsTo(Visite::class,'visite_id');
    }
    public function user(){
        return $this->belongsTo(User::class ,'user_id','user_id_origin');
    }
    public function prospect(){
        return $this->belongsTo(Prospect::class,'prospect_id');
    }
    public function projet(){
        return $this->belongsTo(Projet::class,'projet_id');
    }
    public function reservation(){
        return $this->belongsTo(Reservation::class,'reservation_id');
    }
    public function avance(){
        return $this->belongsTo(Avance::class,'avance_id');
    }

}
