<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Objectif extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='objectifs';
    protected $dates=['deleted_at'];
    protected $with=['user'];

   //pour repmir les json par array
    protected $casts = [
        'visites' => 'array',
        'appels' => 'array',
        'reservations' => 'array'
    ];

    public function user_add(){
        return $this->belongsTo(User::class ,'user_id_add')->withTrashed();
    }
    public function user(){
        return $this->belongsTo(User::class ,'user_id')->withTrashed();
    }

    public function projet(){
        return $this->belongsTo(Projet::class,'projet_id');
    }

}
