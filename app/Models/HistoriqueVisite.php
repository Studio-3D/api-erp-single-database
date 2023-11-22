<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HistoriqueVisite extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'historique_visites';

    protected $dates = ['deleted_at'];
    protected $with = ['user', 'bien'];

    public function user()
    {
        return $this->belongsTo(User::class ,'user_id','user_id_origin');
    }
    public function bien()
    {
        return $this->belongsTo(Bien::class,'bien_id');
    }
    public function visite()
    {
        return $this->belongsTo(Visite::class,'visite_id');
    }
}
