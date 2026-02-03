<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gestion_Roles extends Model
{
    use SoftDeletes;

    protected $table = 'gestion_roles';

    // La connexion sera définie dynamiquement par DatabaseHelper
    protected $connection = 'mysql'; // Connexion par défaut

    protected $fillable = [
        'role',
        'actif'
    ];

    protected $casts = [
        'actif' => 'boolean'
    ];

    // Accessor pour le label du rôle
    public function getRoleLabelAttribute()
    {
        $roleEnum = \App\Enum\RoleEnum::tryFrom($this->role);
        return $roleEnum ? $roleEnum->name : 'Inconnu';
    }

    // Accessor pour la description
    public function getRoleDescriptionAttribute()
    {
        $descriptions = [
            \App\Enum\RoleEnum::COMMERCIAL->value => 'Service Commercial (Visite & Ventes)',
            \App\Enum\RoleEnum::NOTAIRE->value => 'Service Notarial',
            \App\Enum\RoleEnum::RESPO_LIVRAISON->value => 'Responsable Livraison',
            \App\Enum\RoleEnum::COMPTABLE->value => 'Service Comptable',
            \App\Enum\RoleEnum::SAV->value => 'Service Après-Vente (SAV)',
            \App\Enum\RoleEnum::ADMIN_COMMERCIAL->value => 'Administrateur Commercial (Notifications)'
        ];

        return $descriptions[$this->role] ?? 'Description non définie';
    }

    // Scope pour la connexion temp
    public function scopeOnTemp($query)
    {
        return $query->on('temp');
    }
}
