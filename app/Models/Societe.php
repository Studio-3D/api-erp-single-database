<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Societe extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'societes';
    protected $fillable = [
        'adresse',
        'email', 'nom_contact',
        'prenom_contact',
        'raison_sociale', 'tel',
    ];

    protected $dates = ['deleted_at'];

    public function user()
    {
        return $this->hasMany(User::class, 'user_id');
    }

    public function projet()
    {
        return $this->hasMany(Projet::class);
    }
    
}
