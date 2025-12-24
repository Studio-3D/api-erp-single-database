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

    protected $fillable = [
        'lien',
        'date',
        'type',
        'description_type',
        'role',
        'user_id',
        'visite_id',
        'projet_id',
        'prospect_id',
        'avance_id',
        'reservation_id',
        'bien_id',
        'traite_appel_id',
        'seen'
    ];
   
    protected $casts = [
        'seen' => 'array',
        'date' => 'datetime'
    ];

    public function visite(){
        return $this->belongsTo(Visite::class,'visite_id');
    }
    public function user(){
        return $this->belongsTo(User::class ,'user_id','user_id_origin')->withTrashed();
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
    public function bien(){
        return $this->belongsTo(Bien::class,'bien_id');
    }
    public function TraitementAppel(){
        return $this->belongsTo(TraitementAppel::class,'traite_appel_id');
    }

}
