<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proposition extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='propositions';
    protected $dates=['deleted_at'];
    protected $with = ['bien','user'];
    public function bien()
    {
        return $this->belongsTo(Bien::class,  'bien_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class,'user_id'  ,'user_id_origin');
    }
}
