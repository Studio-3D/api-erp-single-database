<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EcheanceProjet extends Model
{
    //Etape Projet
    use HasFactory;

    use SoftDeletes;

    protected $table='echeances_projet';
    protected $dates=['deleted_at'];
    protected $with=['projet'];

    public function projet()
    {
        return $this->belongsTo(Projet::class,'projet_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }
}
