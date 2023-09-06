<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banque extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table='banques';
    protected $dates=['deleted_at'];
}
