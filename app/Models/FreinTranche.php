<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FreinTranche extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='frein_tranches';
    protected $dates=['deleted_at'];
}
