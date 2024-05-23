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
    public function responsable_validation()
    {
        return $this->belongsTo(User::class,'user_id_valider');
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
        return $this->belongsTo(Bien::class,'bien_id_new');
    }
    public function banque()
    {
        return $this->belongsTo(Banque::class,'banque_id');
    }
    public function aquereurs_desisteurs()
    {
        return $this->hasMany(AquereurDesistement::class,'desistement_id')->where('type','desisteur');
    }
    public function aquereurs_non_desisteurs()
    {
        return $this->hasMany(AquereurDesistement::class,'desistement_id')->where('type','non_desisteur');
    }
    public function aquereurs_profits()
    {
        return $this->hasMany(AquereurDesistement::class,'desistement_id')->where('type','au_profit');
    }
    public function aquereurs_partiel()
    {
        return $this->hasMany(AquereurDesistement::class,'desistement_id')->where('type','partiel');
    }

    public function remboursement()
    {
        return $this->hasMany(Remboursement::class,'desistement_id');
    }
    public function nouvel_aquereurs_desistements()
    {
        return $this->hasMany(NouvelAquereurDesistement::class)->whereNotNull('desistement_id');
    }
    public function penalite_desistement()
    {
        return $this->hasOne(PenaliteDesistement::class,'desistement_id');
    }
    public function Piece_jointes()
    {
        return $this->hasMany(PiecesJointe::class,'desistement_id')->where('active',1);
    }
    
    //piece jointe cree par commercial (desistement)
    public function Piece_jointes_des_montant_a_ajouter()
    {
        return $this->hasMany(PiecesJointe::class,'desistement_id')->where('active',0);
    }
    public function Avance()
    {
        return $this->hasOne(Avance::class,'desistement_id')->withTrashed();
    }
}
