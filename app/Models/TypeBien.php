<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TypeBien extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'projet_id',
    ];
    protected $dates = ['deleted_at'];
    public function projet()
    {
        return $this->belongsTo(Projet::class);
    }
}
