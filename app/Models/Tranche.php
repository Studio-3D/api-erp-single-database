<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tranche extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'tranches';
    protected $dates = ['deleted_at'];

    public function projet()
    {
        return $this->belongsTo(Project::class);
    }
    public function tranche()
    {
        return $this->belongsTo(Tranche::class);
    }
}
