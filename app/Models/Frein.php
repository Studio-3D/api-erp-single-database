<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Frein extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='freins';
    protected $dates=['deleted_at'];

    public function  tranche()
    {
        return $this->belongsToMany(Tranche::class,'frein_tranches');
    }
    public function  typologie(){
        return $this->belongsToMany(Typologie::class,'frein_typologies');
    }
    public function  vue(){
        return $this->belongsToMany(Vue::class,'frein_vues');
    }

    public function visite(){
        return $this->belongsTo(Visite::class,'visite_id');
    }

}
