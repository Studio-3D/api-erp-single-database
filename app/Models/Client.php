<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory;


    use SoftDeletes;

    protected $table='clients';
    protected $dates=['deleted_at'];
    protected $with = ['partenaire'];


    public function reservations()
    {
        return $this->belongsToMany(Reservation::class, 'aquereurs', 'client_id', 'reservation_id');
    }

    public function aquereur()
    {
       return $this->hasMany(Aquereur::class);
    }

    public function prospect()
    {
        return $this->belongsTo(Prospect::class,'prospect_id');
    }

    public function partenaire()
    {
        return $this->belongsTo(Partenaire::class,'partenaire_id');
    }


    public function aquereur_desistement()
    {
       return $this->hasMany(AquereurDesistement::class);
    }

    public function reclamation()
    {
        return $this->hasMany(Reclamation::class);
    }



}
