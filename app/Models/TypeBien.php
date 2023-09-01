<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TypeBien extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'type_biens';
    protected $fillable = [
        'type'
    ];
    protected $dates = ['deleted_at'];
    public function bien()
    {
        return $this->hasMany(Bien::class);
    }
}
