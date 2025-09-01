<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HistoriqueAvance extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table='historique_avances';
    protected $dates=['deleted_at'];
    protected $with = [];

    public function avance()
    {
        return $this->belongsTo(Avance::class,'avance_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }
    public function banque()
    {
        return $this->belongsTo(Banque::class,'banque_id');
    }
}
