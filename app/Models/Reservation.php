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
    protected $with = ['bien', 'user', 'projet','aquereurs','aquereurs_ancien','historiques'];


    public function visite(){
        return $this->belongsTo(Visite::class,'visite_id');
    }

    public function bien(){
        return $this->belongsTo(Bien::class,'bien_id');
    }

    public function client(){
        return $this->belongsTo(Client::class);
    }


    public function user(){
        return $this->belongsTo(User::class,'user_id');
    }

    public function projet(){
        return  $this->belongsTo(Projet::class,'projet_id');
    }
    public function aquereurs()
    {
        return $this->hasMany(Aquereur::class,'reservation_id');
    }
    public function aquereurs_ancien()
    {
        return $this->hasMany(Aquereur::class,'reservation_id')->onlyTrashed();
    }
    public function avances()
    {
        return $this->hasMany(Avance::class,'reservation_id');
    }
    public function avances_desist()
    {
        return $this->hasMany(Avance::class,'reservation_id')->onlyTrashed();
    }
    public function piece_jointe()
    {
        return $this->hasMany(PiecesJointe::class,'reservation_id');
    }
    public function piece_jointe_desiste()
    {
        return $this->hasMany(PiecesJointe::class,'reservation_id')->onlyTrashed();
    }
    public function historiques()
    {
        return $this->hasMany(HistoReservation::class,'reservation_id');
    }

    public function desistements_valide()
    {
        return $this->hasMany(Desistement::class,'reservation_id')->where('statut',1);
    }

}
