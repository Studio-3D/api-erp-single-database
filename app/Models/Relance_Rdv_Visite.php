<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Relance_Rdv_Visite extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'relances_rdv_visites';

    protected $dates = ['deleted_at'];
    protected $with = ['user', 'visite','user_traite'];

    public function user()
    {
        return $this->belongsTo(User::class ,'user_id','id')->withTrashed();
    }
    public function user_traite()
    {
        return $this->belongsTo(User::class ,'user_id_traite','id')->withTrashed();
    }

    public function visite()
    {
        return $this->belongsTo(Visite::class,'visite_id');
    }
}
