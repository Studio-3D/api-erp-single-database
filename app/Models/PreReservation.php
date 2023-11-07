<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreReservation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='pre_reservations';
    protected $dates=['deleted_at'];
    protected $with = ['bien'];
    public function bien()
    {
        return $this->belongsTo(Bien::class,  'bien_id');
    }
    public function visite()
    {
        return $this->belongsTo(Visite::class,  'visite_id');
    }
}
