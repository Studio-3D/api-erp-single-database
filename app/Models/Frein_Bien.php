<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Frein_Bien extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='freins_biens';
    protected $dates=['deleted_at'];
    protected $with = ['bien','frein'];
    public function bien()
    {
        return $this->belongsTo(Bien::class,  'bien_id');
    }

    public function frein()
    {
        return $this->belongsTo(Frein::class,'frein_id');
    }
    public function is_proposed()
    {
        return $this->hasone(Proposition::class,'bien_id','bien_id')->orderby('created_at','asc')->latest();
    }
}
