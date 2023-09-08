<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TypeBienProjet extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'user_projets';
    protected $dates = ['deleted_at'];
    

    public function projet()
    {
        return $this->belongsTo(Projet::class, 'projet_id');
    }

    public function typeBien()
    {
        return $this->belongsTo(TypeBien::class, 'type_bien_id');
    }
}
