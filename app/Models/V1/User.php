<?php

namespace App\Models\V1;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Avance;
use App\Models\Compromis_vente;
use App\Models\Contrat_vente;
use App\Models\Desistement;
use App\Models\Projet;
use App\Models\Reservation;
use App\Models\TraitementAppel;
use App\Models\Visite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'societe_id',
        'name',
        'email',
        'password',
        'prenom',
        'gender',
        'type',
        'phone',
        'photo',
        'nb_appel_recu',
        'nb_appel_traite',
        'cin',
        'date_embauche',
        'niveau_etude',
        'adresse',
        'cnss',
        'fonction',
        'solde_conge',
        'is_actif',
    ];
    protected $table = 'users';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    protected $dates = ['deleted_at'];
    protected $with = ['societe'];

    public function societe()
    {
        // Ca doit changer apres en utilisant la version qu'il faut du modele Projet
        return $this->belongsTo(\App\Models\Societe::class, 'societe_id');
    }

    public function projet()
    {
        // Ca doit changer apres en utilisant la version qu'il faut du modele Projet
        return $this->belongsToMany(Projet::class);
    }

    public function projets()
    {
        return $this->belongsToMany(Projet::class, 'user_projets', 'user_id', 'projet_id');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'user_id');
    }

    public function desistements()
    {
        return $this->hasMany(Desistement::class, 'user_id');
    }

    public function visites()
    {
        return $this->hasMany(Visite::class, 'user_id');
    }

    public function avances()
    {
        return $this->hasMany(Avance::class, 'user_id');
    }
    public function compromis_ventes()
    {
        return $this->hasMany(Compromis_vente::class, 'user_id');
    }
    public function contrat_ventes()
    {
        return $this->hasMany(Contrat_vente::class, 'user_id');
    }
    public function traitement_appels()
    {
        return $this->hasMany(TraitementAppel::class, 'user_id');
    }
    
}
