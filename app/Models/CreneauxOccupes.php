<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreneauxOccupes extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table='creneaux_occupes';
    protected $dates=['deleted_at'];
      protected $casts = [
        'debut' => 'datetime',
        'fin' => 'datetime',
        'disponible' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }
}
