<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Typologie extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='typologies';
    protected $dates=['deleted_at'];
    public function  frein(){
        return $this->belongsToMany(Frein::class,'frein_typologies');
    }

    public function projet(){
        return $this->belongsTo(Projet::class,'projet_id');
    }
}
