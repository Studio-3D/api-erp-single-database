<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Encaissement extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'encaissements';

    protected $dates = ['deleted_at'];
    protected $with = ['avance'];

    public function user()
    {
        return $this->belongsTo(User::class ,'user_id_valider');
    }

    public function avance()
    {
        return $this->belongsTo(Avance::class,'avance_id');
    }
}
