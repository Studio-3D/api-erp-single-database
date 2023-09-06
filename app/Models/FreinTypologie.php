<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FreinTypologie extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='frein_typologies';
    protected $dates=['deleted_at'];
}
