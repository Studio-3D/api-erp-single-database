<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Avance extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table='avances';
    protected $dates=['deleted_at'];

    public function banque()
    {
        return $this->belongsTo(Banque::class,'banque_id');
    }
}
