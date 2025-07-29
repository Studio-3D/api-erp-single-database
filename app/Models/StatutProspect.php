<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatutProspect extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='statut_prospects';
    protected $with = ['prospect','user'];
    protected $dates=['deleted_at'];

    public function prospect(){
        return $this->belongsTo(Prospect::class,'prospect_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,'user_id_traite')->withTrashed();
    }

}
