<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Societe extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'societes';
    protected $fillable = [
        'adresse',
        'email',
        'nom_contact',
        'prenom_contact',
        'raison_sociale',
        'raison_sociale_concatene',
        'tel',
        'societe_id',
        'capital',
        'id_fiscal',
        'registre_commerce',
    ];

    protected $dates = ['deleted_at'];

    public function user()
    {
        return $this->hasMany(User::class, 'user_id');
    }

    public function client()
    {
        return $this->hasMany(\App\Models\Client::class);
    }

    public function projet()
    {
        return $this->hasMany(\App\Models\Projet::class);
    }

}
