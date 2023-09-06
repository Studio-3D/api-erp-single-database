<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FreinOrientation extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table='frein_orientations';
    protected $dates=['deleted_at'];


    public function frein(){
        return $this->belongsTo(Frein::class,'frein_id');
    }
}
