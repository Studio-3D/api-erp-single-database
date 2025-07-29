<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class UserProjet extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'user_projets';
    protected $dates = ['deleted_at'];

    protected $with=['user'];

    public function projet()
    {
        return $this->belongsTo(Projet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }


   /* public function user()
    {
        return $this->belongsTo(User::class,'user_id','user_id_origin');
    }*/
}
