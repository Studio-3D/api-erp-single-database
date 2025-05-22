<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicesPrestataires extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='services_prestataires';
    protected $dates=['deleted_at'];

    public function prestataires()
    {
        return $this->hasMany(Prestataire::class,'service_id');
    }
    
    public function reclamations()
    {
        return $this->hasMany(Reclamation::class,'prestataire_id');
    }
}
