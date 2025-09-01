<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Avance extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table='avances';
    protected $dates=['deleted_at'];
    protected $with = ['banque','user','reservation','piece_jointe'];

    public function banque()
    {
        return $this->belongsTo(Banque::class,'banque_id');
    }
    public function reservation(){

        return $this->belongsTo(Reservation::class,'reservation_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }
    public function encaissement()
    {
        return $this->hasOne(Encaissement::class,'avance_id');
    }
    public function historiques()
    {
        return $this->hasMany(HistoriqueAvance::class,'avance_id');
    }
    public function piece_jointe()
    {
        return $this->hasMany(PiecesJointe::class, 'avance_id')->where('active',1);
    }
    public function all_piece_jointe()
    {
        return $this->hasMany(PiecesJointe::class, 'avance_id');
    }
    public function last_statut()
    {
        return $this->hasOne(StatutAvancePenalite::class,'avance_id')->latest();
    }

}
