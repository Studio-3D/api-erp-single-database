<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Immeuble extends Model
{
    use HasFactory, SoftDeletes;
     protected $table = 'immeubles';
    protected $dates = ['deleted_at'];
    public function projet()
    {
        return $this->belongsTo(Projet::class);
    }

    public function tranche()
    {
        return $this->belongsTo(Tranche::class);
    }

    public function bloc()
    {
        return $this->belongsTo(Bloc::class);
    }
}
