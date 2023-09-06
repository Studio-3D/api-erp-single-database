<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prospect extends Model
{
    use HasFactory;
    use SoftDeletes;
    /**
     * table associer a le model
     */
    protected $table='prospects';
    protected $dates=['deleted_at'];
}
