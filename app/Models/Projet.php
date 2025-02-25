<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Projet extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'projets';
    /* protected $fillable = [
        'nom', 'code', 'adresse',
        'date_autorisation_construction',
        'date_permis_habiter', 'surface_terrain', 'prix_acquisition',
        'limite_annulation_reservation', 'nbre_tranches',
        'nbre_blocs', 'nbre_immeubles', 'nbre_biens', 'type_id',
    ]; */

    protected $dates = ['deleted_at'];
    protected $with=['typeProjet','userProjet','societe'];

    public function typeProjet()
    {
        return $this->belongsTo(TypeProjet::class, 'type_id');
    }

    public function societe()
    {
        return $this->belongsTo(Societe::class, 'societe_id');
    }
    public function tranche()
    {
        return $this->hasMany(Tranche::class, 'projet_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_projets', 'projet_id', 'user_id');
    }
    public function user()
   {
       return $this->belongsToMany(User::class);
   }
   //protected $with=['bien'];
   public function bien()
   {
       return $this->hasMany(Bien::class);
   }
   public function bloc()
   {
       return $this->hasMany(Bloc::class);
   }
   public function immeuble()
   {
       return $this->hasMany(Immeuble::class);
   }

   public function userProjet()
   {
       return $this->hasMany(userProjet::class);
   }
   public function typesBien()
   {
       return $this->hasMany(TypeBien::class);
   }
   public function typologies()
   {
       return $this->hasMany(Typologie::class);
   }
   public function vues()
   {
       return $this->hasMany(Vue::class);
   }

   public function prospects()
   {
       return $this->hasMany(Prospect::class);
   }
   public function partenaires()
   {
       return $this->hasMany(Partenaire::class);
   }

   public function visites()
   {
       return $this->hasMany(Visite::class);
   }
   public function reservations()
   {
       return $this->hasMany(Reservation::class);
   }
   public function appels()
   {
       return $this->hasMany(Appel::class);
   }
   public function clients()
   {
       return $this->hasMany(Client::class);
   }
   public function notifications()
   {
       return $this->hasMany(Notification::class);
   }
   public function fournisseurs()
   {
       return $this->hasMany(Fournisseur::class);
   }
   public function decomptes()
   {
       return $this->hasMany(Decompte::class);
   }
   public function factures()
   {
       return $this->hasMany(Decompte::class);
   }
   public function cps()
   {
       return $this->hasMany(Cps::class);
   }
   public function credits()
   {
       return $this->hasMany(Cps::class);
   }
   public function objectifs()
   {
       return $this->hasMany(Objectif::class);
   }

   public function reclamations()
   {
       return $this->hasMany(Reclamation::class);
   }
   public function remise_cles()
   {
       return $this->hasMany(RemiseCle::class);
   }

   public function import()
   {
       return $this->hasMany(Import::class);
   }
   public function echeance_projet()
   {
       return $this->hasMany(EcheanceProjet::class);
   }
}
