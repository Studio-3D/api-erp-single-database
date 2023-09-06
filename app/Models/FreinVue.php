<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FreinVue extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='frein_vues';
    protected $dates=['deleted_at'];
}
