<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cps extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table='cps';
    protected $dates=['deleted_at'];
    public function user()
    {
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }
}
