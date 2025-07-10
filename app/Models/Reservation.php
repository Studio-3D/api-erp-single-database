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
    protected $with = ['bien', 'user', 'projet','aquereurs','aquereurs_ancien','historiques','piece_jointe'];


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
    public function avances_valides()
    {
        return $this->hasMany(Avance::class,'reservation_id')->where('statut',1);
    }
    public function rdv()
    {
        return $this->hasMany(Rendez_vous::class,'reservation_id');
    }
    public function compromis_vente()
    {
        return $this->hasOne(Compromis_vente::class,'reservation_id')->orderby('created_at','asc')->latest();
    }
    public function contrat_vente()
    {
        return $this->hasOne(Contrat_vente::class,'reservation_id')->orderby('created_at','asc')->latest();
    }
    public function first_avance()
    {
        return $this->hasOne(Avance::class,'reservation_id')->orderby('created_at','asc')->latest();
    }
    public function avances_desist()
    {
        return $this->hasMany(Avance::class,'reservation_id')->onlyTrashed();
    }
    public function piece_jointe()
    {
        return $this->hasMany(PiecesJointe::class,'reservation_id')->where('active',1);
    }
    public function piece_jointe_desiste()
    {
        return $this->hasMany(PiecesJointe::class,'reservation_id')->where('active',1)->onlyTrashed();
    }
    public function historiques()
    {
        return $this->hasMany(HistoReservation::class,'reservation_id')->orderby('created_at','desc');
    }

    public function desistements_valide()
    {
        return $this->hasMany(Desistement::class,'reservation_id')->where('statut',1);
    }
    public function desistement_att_validation_rejete(){
        return $this->hasOne(Desistement::class)->whereIn('statut',[0,2])->where('archive',0)->latest();
    }
    public function desistements_ancien(){
        return $this->hasOne(Desistement::class,'reservation_id_new')->where('statut',1)->whereIn('type',[2,3])->where('archive',0)->latest();
    }

    public function remboursement_dd_with_transfert(){
        return $this->hasOne(Remboursement::class)->where('statut',1)->where('mode_rembourse','transfert_rem_apres_vente')->orwhere('mode_rembourse','transfert_rem_direct')->orwhere('mode_rembourse','transfert')->latest();
    }
    public function last_statut()
    {
        return $this->hasOne(StatutReservation::class,'reservation_id')->latest();
    }

}
