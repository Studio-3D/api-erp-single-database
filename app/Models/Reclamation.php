<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reclamation extends Model
{
    use HasFactory;


    use SoftDeletes;

    protected $table='reclamations';
    protected $dates=['deleted_at'];
    protected $with = ['prestataire','bien','client','service'];
    //pour repmir les json par array


    public function client()
    {
        return $this->belongsTo(Client::class,'client_id');
    }

    public function prestataire()
    {
        return $this->belongsTo(Prestataire::class,'prestataire_id');
    }
    public function service()
    {
        return $this->belongsTo(ServicesPrestataires::class,'service_id');
    }

    public function bien()
    {
        return $this->belongsTo(Bien::class,'bien_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,'user_id_traitee')->withTrashed();
    }
    public function piece_jointe()
    {
        return $this->hasMany(PiecesJointe::class, 'reclamation_id')->where('active',1);
    }

}
