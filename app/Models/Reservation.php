<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table='reservations';
    protected $dates=['deleted_at'];

    public function visite(){
        return $this->belongsTo(Visite::class,'visite_id');
    }

    public function bien(){
        return $this->belongsTo(Bien::class,'bien_id');
    }

    public function user(){
        return $this->belongsTo(User::class,'user_id');
    }

    public function banque(){
        return $this->belongsTo(Banque::class,'banque_id');
    }

    public function projet(){
        return  $this->belongsTo(Projet::class,'projet_id');
    }
}
