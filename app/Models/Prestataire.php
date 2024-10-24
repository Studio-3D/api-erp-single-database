<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prestataire extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='prestataires';
    protected $dates=['deleted_at'];
    protected $with=['service'];

    public function service()
    {
        return $this->belongsTo(ServicesPrestataires::class, 'service_id');
    }
    public function reclamations()
    {
        return $this->hasMany(Reclamation::class,'prestataire_id');
    }
}
