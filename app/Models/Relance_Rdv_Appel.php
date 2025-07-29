<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Relance_Rdv_Appel extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'relances_rdvs_appels';

    protected $dates = ['deleted_at'];
    protected $with = ['user', 'traite_appel','user_traite'];

    public function user()
    {
        return $this->belongsTo(User::class ,'user_id','id')->withTrashed();
    }
    public function user_traite()
    {
        return $this->belongsTo(User::class ,'user_id_traite','id')->withTrashed();
    }

    public function traite_appel()
    {
        return $this->belongsTo(TraitementAppel::class,'traite_appel_id');
    }
}
