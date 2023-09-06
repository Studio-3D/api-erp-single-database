<?php

namespace App\Models;

use App\Enum\InteretEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visite extends Model
{
    use HasFactory;
    use SoftDeletes;
    /**
     * table associer a le model
     */
    protected $table='visites';
    protected $dates=['deleted_at'];
    protected $casts=['rdv'=>'datetime:Y-m-d\TH:i'];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
    public function prospect()
    {
        return $this->belongsTo(Prospect::class,'prospect_id');
    }
    public function bien()
    {
        return $this->belongsTo(Bien::class,'bien_id');
    }

    public function source(){
        return $this->belongsTo(Source::class,'source_id');
    }
}
