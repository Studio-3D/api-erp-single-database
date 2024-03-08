<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Desistement extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $with=['reservation_ancien','Bien_ancien','user'];

    protected $table='desistements';
    protected $dates=['deleted_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservation(){
        return $this->belongsTo(Reservation::class,'reservation_id_new');
    }
    public function reservation_ancien(){
        return $this->belongsTo(Reservation::class,'reservation_id');
    }
    public function Bien_ancien()
    {
        return $this->belongsTo(Bien::class,'bien_id_ancien');
    }
    public function Bien_nouveau()
    {
        return $this->belongsTo(Bien::class,'bien_id');
    }
    public function aquereurs_desistements()
    {
        return $this->hasMany(AquereurDesistement::class);
    }


    public function nouvel_aquereurs_desistements()
    {
        return $this->hasMany(NouvelAquereurDesistement::class)->whereNotNull('desistement_id');
    }
}
